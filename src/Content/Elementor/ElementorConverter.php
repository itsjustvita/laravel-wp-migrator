<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Elementor;

use Closure;
use ItsJustVita\WpMigrator\Content\Autop;
use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\SafeUrl;
use JsonException;

/**
 * Turns the element tree Elementor stores in _elementor_data into blocks.
 *
 * Containers become groups, sections and columns become columns, and the
 * common widgets become the core blocks with the markup the block editor
 * would write. Global widgets and templates are replaced by their content.
 * Every other widget, including those of other plugins and of the theme,
 * becomes a block named elementor/<widget> with all its settings as
 * attributes, so a view can render it. The design (colors, fonts, spacing)
 * is not converted.
 *
 * Fields that hold HTML in Elementor keep it, like post content does; plain
 * text fields are escaped, and links that could run script are left out.
 */
class ElementorConverter
{
    /** Video providers Elementor embeds by URL, and the setting holding it. */
    private const array EMBEDS = [
        'youtube' => 'youtube_url',
        'vimeo' => 'vimeo_url',
        'dailymotion' => 'dailymotion_url',
    ];

    private const string SPACER_UNITS = '/^(px|em|rem|vh|vw|%)$/';

    /** Templates one page may expand, and how deeply they may nest. */
    private const int MAX_EXPANSIONS = 200;

    private const int MAX_NESTING = 10;

    /** @var Closure(string): void */
    private Closure $warn;

    /** @var array<int, true> templates being expanded, to stop cycles */
    private array $expanding = [];

    private int $expansions = 0;

    private bool $overBudget = false;

    /** @var array<int, list<mixed>|null> decoded templates by ID */
    private array $templates = [];

    public function __construct(
        private readonly Autop $autop,
        private readonly WpDatabase $db,
    ) {}

    /**
     * Whether the post was last edited with Elementor. A page switched back
     * to the block editor keeps its old Elementor data, which no longer counts.
     */
    public static function builtWith(MetaBag $meta): bool
    {
        return $meta->get('_elementor_edit_mode') === 'builder'
            && ! in_array(trim((string) $meta->get('_elementor_data')), ['', '[]'], true);
    }

    /**
     * @param  Closure(string): void  $warn  receives what could not be converted
     * @return list<Block>|null null when the data is not a list of elements
     */
    public function convert(string $json, Closure $warn): ?array
    {
        $elements = $this->decode($json);

        if ($elements === null) {
            return null;
        }

        $this->warn = $warn;
        $this->expansions = 0;
        $this->overBudget = false;

        return $this->elements($elements);
    }

    /**
     * @return list<mixed>|null
     */
    private function decode(string $json): ?array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) && array_is_list($data) ? $data : null;
    }

    /**
     * @param  array<array-key, mixed>  $elements
     * @return list<Block>
     */
    private function elements(array $elements): array
    {
        $blocks = [];

        foreach ($elements as $element) {
            if (is_array($element)) {
                array_push($blocks, ...$this->element($element));
            }
        }

        return $blocks;
    }

    /**
     * @param  array<array-key, mixed>  $element
     * @return list<Block>
     */
    private function element(array $element): array
    {
        $settings = $this->settings($element);
        $children = is_array($element['elements'] ?? null) ? $element['elements'] : [];

        return match ($element['elType'] ?? null) {
            'widget' => $this->widget($element, $settings),
            'container' => $this->container($children, $settings),
            'section' => $this->section($children),
            default => $this->elements($children),
        };
    }

    /**
     * @param  array<array-key, mixed>  $children
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function container(array $children, array $settings): array
    {
        $inner = $this->elements($children);

        if ($inner === []) {
            return [];
        }

        if (in_array($settings['flex_direction'] ?? null, ['row', 'row-reverse'], true)) {
            return [$this->wrapper('core/group', ['layout' => ['type' => 'flex', 'flexWrap' => 'nowrap']], '<div class="wp-block-group is-layout-flex">', '</div>', $inner)];
        }

        return [$this->group($inner)];
    }

    /**
     * A section holds columns. One column needs no columns block.
     *
     * @param  array<array-key, mixed>  $children
     * @return list<Block>
     */
    private function section(array $children): array
    {
        $columns = [];
        $empty = true;

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $inner = $this->elements(is_array($child['elements'] ?? null) ? $child['elements'] : []);
            $empty = $empty && $inner === [];
            $columns[] = [$this->columnWidth($this->settings($child)), $inner];
        }

        if ($empty) {
            return [];
        }

        if (count($columns) === 1) {
            return [$this->group($columns[0][1])];
        }

        $blocks = array_map(function (array $column): Block {
            [$width, $inner] = $column;

            return $width === null
                ? $this->wrapper('core/column', [], '<div class="wp-block-column">', '</div>', $inner)
                : $this->wrapper('core/column', ['width' => $width], '<div class="wp-block-column" style="flex-basis:'.$width.'">', '</div>', $inner);
        }, $columns);

        return [$this->wrapper('core/columns', [], '<div class="wp-block-columns">', '</div>', $blocks)];
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    private function columnWidth(array $settings): ?string
    {
        foreach (['_inline_size', '_column_size'] as $key) {
            if (is_numeric($settings[$key] ?? null) && (float) $settings[$key] > 0) {
                return $this->number($settings[$key]).'%';
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $element
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function widget(array $element, array $settings): array
    {
        $type = $element['widgetType'] ?? null;

        if (! is_string($type)) {
            return [];
        }

        return match ($type) {
            'heading' => $this->heading($this->text($settings, 'title'), $settings['header_size'] ?? 'h2', $settings['link'] ?? null),
            'text-editor' => $this->richText($this->text($settings, 'editor')),
            'image' => $this->image($settings['image'] ?? null, $settings),
            'button' => $this->button($settings),
            'video' => $this->video($settings),
            'divider' => [new Block('core/separator', [], '<hr class="wp-block-separator has-alpha-channel-opacity"/>')],
            'spacer' => $this->spacer($settings),
            'icon-list' => $this->iconList($settings),
            'html' => $this->raw('core/html', $this->text($settings, 'html')),
            'shortcode' => $this->raw('core/shortcode', $this->text($settings, 'shortcode')),
            'image-gallery' => $this->gallery($settings),
            'icon-box' => $this->box('elementor-icon-box', [], $settings),
            'image-box' => $this->box('elementor-image-box', $this->image($settings['image'] ?? null, []), $settings),
            'testimonial' => $this->testimonial($settings),
            'accordion', 'toggle', 'tabs' => $this->tabs($settings),
            'global' => $this->template($element['templateID'] ?? null),
            'template' => $this->template($settings['template_id'] ?? null),
            default => $this->unknown($type, $settings),
        };
    }

    /**
     * @return list<Block>
     */
    private function heading(string $title, mixed $size, mixed $link): array
    {
        if ($title === '') {
            return [];
        }

        $title = $this->linked($title, $link);

        if (is_string($size) && preg_match('/^h([1-6])$/', $size, $matches) === 1) {
            $level = (int) $matches[1];

            return [new Block('core/heading', ['level' => $level], '<h'.$level.' class="wp-block-heading">'.$title.'</h'.$level.'>')];
        }

        return [$this->paragraph($title)];
    }

    /**
     * @return list<Block>
     */
    private function richText(string $html): array
    {
        return $html === '' ? [] : [Block::freeform($this->autop->format($html))];
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function image(mixed $image, array $settings): array
    {
        $url = is_array($image) ? $this->text($image, 'url') : '';

        if (! is_array($image) || $url === '') {
            return [];
        }

        if (! SafeUrl::isSafe($url)) {
            return [];
        }

        $id = is_numeric($image['id'] ?? null) && (int) $image['id'] > 0 ? (int) $image['id'] : null;
        $size = $this->text($settings, 'image_size');
        $size = $size === '' ? 'large' : $size;

        $attrs = $id === null ? [] : ['id' => $id];
        $attrs['sizeSlug'] = $size;

        $html = sprintf(
            '<img src="%s" alt="%s"%s/>',
            $this->escape($url),
            $this->escape($this->text($image, 'alt')),
            $id === null ? '' : ' class="wp-image-'.$id.'"',
        );

        [$href, $destination] = match ($settings['link_to'] ?? null) {
            'file' => [$url, 'media'],
            'custom' => [$this->url($settings['link'] ?? null), 'custom'],
            default => ['', null],
        };

        if ($href !== '') {
            $attrs['linkDestination'] = $destination;
            $html = '<a href="'.$this->escape($href).'">'.$html.'</a>';
        }

        $caption = ($settings['caption_source'] ?? null) === 'custom' ? $this->text($settings, 'caption') : '';

        if ($caption !== '') {
            $html .= '<figcaption class="wp-element-caption">'.$this->escape($caption).'</figcaption>';
        }

        return [new Block('core/image', $attrs, '<figure class="wp-block-image size-'.$this->escape($size).'">'.$html.'</figure>')];
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function button(array $settings): array
    {
        $text = $this->text($settings, 'text');

        if ($text === '') {
            return [];
        }

        $link = $settings['link'] ?? null;
        $url = $this->url($link);
        $href = $url === '' ? '' : ' href="'.$this->escape($url).'"'.$this->linkAttributes($link);

        $button = new Block('core/button', [], '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button"'.$href.'>'.$this->escape($text).'</a></div>');

        return [$this->wrapper('core/buttons', [], '<div class="wp-block-buttons">', '</div>', [$button])];
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function video(array $settings): array
    {
        $type = $this->text($settings, 'video_type');
        $type = $type === '' ? 'youtube' : $type;

        if (isset(self::EMBEDS[$type])) {
            $url = $this->text($settings, self::EMBEDS[$type]);

            if ($url === '' || ! SafeUrl::isSafe($url)) {
                return [];
            }

            return [new Block(
                'core/embed',
                ['url' => $url, 'type' => 'video', 'providerNameSlug' => $type],
                sprintf("<figure class=\"wp-block-embed is-type-video is-provider-%s wp-block-embed-%s\"><div class=\"wp-block-embed__wrapper\">\n%s\n</div></figure>", $type, $type, $this->escape($url)),
            )];
        }

        if ($type !== 'hosted') {
            return $this->unknown('video', $settings);
        }

        $external = ($settings['insert_url'] ?? null) === 'yes';
        $media = $settings[$external ? 'external_url' : 'hosted_url'] ?? null;
        $url = $this->url($media);

        if ($url === '') {
            return [];
        }

        $id = ! $external && is_array($media) && is_numeric($media['id'] ?? null) && (int) $media['id'] > 0 ? (int) $media['id'] : null;

        return [new Block('core/video', $id === null ? [] : ['id' => $id], '<figure class="wp-block-video"><video controls src="'.$this->escape($url).'"></video></figure>')];
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function spacer(array $settings): array
    {
        $space = is_array($settings['space'] ?? null) ? $settings['space'] : [];
        $size = is_numeric($space['size'] ?? null) ? $this->number($space['size']) : '50';
        $unit = is_string($space['unit'] ?? null) && preg_match(self::SPACER_UNITS, $space['unit']) === 1 ? $space['unit'] : 'px';
        $height = $size.$unit;

        return [new Block('core/spacer', ['height' => $height], '<div style="height:'.$height.'" aria-hidden="true" class="wp-block-spacer"></div>')];
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function iconList(array $settings): array
    {
        $items = [];

        foreach ($this->items($settings, 'icon_list') as $item) {
            $text = $this->text($item, 'text');

            if ($text !== '') {
                $items[] = new Block('core/list-item', [], '<li>'.$this->linked($text, $item['link'] ?? null).'</li>');
            }
        }

        return $items === [] ? [] : [$this->wrapper('core/list', [], '<ul class="wp-block-list">', '</ul>', $items)];
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function gallery(array $settings): array
    {
        $images = [];

        foreach ($this->items($settings, 'wp_gallery') as $item) {
            array_push($images, ...$this->image($item, []));
        }

        return $images === [] ? [] : [$this->wrapper('core/gallery', ['linkTo' => 'none'], '<figure class="wp-block-gallery has-nested-images columns-default">', '</figure>', $images)];
    }

    /**
     * An icon box or image box: an image, a title and a description.
     *
     * @param  list<Block>  $image
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function box(string $class, array $image, array $settings): array
    {
        $size = $this->text($settings, 'title_size');
        $description = $this->text($settings, 'description_text');

        $inner = [
            ...$image,
            ...$this->heading($this->text($settings, 'title_text'), $size === '' ? 'h3' : $size, $settings['link'] ?? null),
            ...($description === '' ? [] : [$this->paragraph($description)]),
        ];

        return $inner === [] ? [] : [$this->wrapper('core/group', ['className' => $class], '<div class="wp-block-group '.$class.'">', '</div>', $inner)];
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function testimonial(array $settings): array
    {
        $content = $this->text($settings, 'testimonial_content');
        $cite = $this->escape(implode(', ', array_filter([$this->text($settings, 'testimonial_name'), $this->text($settings, 'testimonial_job')], fn (string $part): bool => $part !== '')));

        if ($content === '' && $cite === '') {
            return [];
        }

        $html = ($content === '' ? '' : '<p>'.$content.'</p>').($cite === '' ? '' : '<cite>'.$cite.'</cite>');

        return [new Block('core/quote', [], '<blockquote class="wp-block-quote">'.$html.'</blockquote>')];
    }

    /**
     * Accordions, toggles and tabs: one details block per item.
     *
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function tabs(array $settings): array
    {
        $blocks = [];

        foreach ($this->items($settings, 'tabs') as $tab) {
            $title = $this->text($tab, 'tab_title');
            $inner = $this->richText($this->text($tab, 'tab_content'));

            if ($title === '' && $inner === []) {
                continue;
            }

            $open = '<details class="wp-block-details"><summary>'.$this->escape($title).'</summary>';

            $blocks[] = $this->wrapper('core/details', [], $open, '</details>', $inner);
        }

        return $blocks;
    }

    /**
     * The content of a global widget or a template from the library.
     *
     * @return list<Block>
     */
    private function template(mixed $id): array
    {
        if (! is_numeric($id) || (int) $id <= 0) {
            return [];
        }

        $id = (int) $id;

        if (isset($this->expanding[$id])) {
            ($this->warn)(sprintf('Elementor template %d includes itself; the repetition was left out.', $id));

            return [];
        }

        if ($this->expansions >= self::MAX_EXPANSIONS || count($this->expanding) >= self::MAX_NESTING) {
            if (! $this->overBudget) {
                ($this->warn)(sprintf('Elementor templates were left out from template %d on: the page uses them more than %d times or nests them more than %d deep.', $id, self::MAX_EXPANSIONS, self::MAX_NESTING));
                $this->overBudget = true;
            }

            return [];
        }

        $this->expansions++;
        $elements = $this->loadTemplate($id);

        if ($elements === null) {
            ($this->warn)(sprintf('Elementor template %d was not found; its content is missing.', $id));

            return [];
        }

        $this->expanding[$id] = true;

        try {
            return $this->elements($elements);
        } finally {
            unset($this->expanding[$id]);
        }
    }

    /**
     * The elements of a template of the library, read once per template.
     *
     * @return list<mixed>|null
     */
    private function loadTemplate(int $id): ?array
    {
        if (array_key_exists($id, $this->templates)) {
            return $this->templates[$id];
        }

        $posts = $this->db->tableName('posts');
        $meta = $this->db->tableName('postmeta');

        $json = $this->db->table('posts')
            ->join($meta, $meta.'.post_id', '=', $posts.'.ID')
            ->where($posts.'.ID', $id)
            ->where($posts.'.post_type', 'elementor_library')
            ->where($meta.'.meta_key', '_elementor_data')
            ->value($meta.'.meta_value');

        return $this->templates[$id] = is_string($json) ? $this->decode($json) : null;
    }

    /**
     * A widget the package does not convert, kept with all its settings.
     *
     * @param  array<array-key, mixed>  $settings
     * @return list<Block>
     */
    private function unknown(string $type, array $settings): array
    {
        $name = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower($type)), '-_');

        if ($name === '') {
            return [];
        }

        if (preg_match('/^[a-z]/', $name) !== 1) {
            $name = 'w-'.$name;
        }

        return [new Block('elementor/'.$name, $settings)];
    }

    /**
     * @return list<Block>
     */
    private function raw(string $name, string $html): array
    {
        return $html === '' ? [] : [new Block($name, [], $html)];
    }

    /**
     * @param  list<Block>  $inner
     */
    private function group(array $inner): Block
    {
        return $this->wrapper('core/group', ['layout' => ['type' => 'constrained']], '<div class="wp-block-group">', '</div>', $inner);
    }

    private function paragraph(string $html): Block
    {
        return new Block('core/paragraph', [], '<p>'.$html.'</p>');
    }

    /**
     * A block around inner blocks, stored the way the block parser stores one.
     *
     * @param  array<array-key, mixed>  $attrs
     * @param  list<Block>  $inner
     */
    private function wrapper(string $name, array $attrs, string $open, string $close, array $inner): Block
    {
        if ($inner === []) {
            return new Block($name, $attrs, $open.$close);
        }

        return new Block($name, $attrs, $open.$close, $inner, [$open, ...array_fill(0, count($inner), null), $close]);
    }

    private function linked(string $html, mixed $link): string
    {
        $url = $this->url($link);

        return $url === '' ? $html : '<a href="'.$this->escape($url).'"'.$this->linkAttributes($link).'>'.$html.'</a>';
    }

    private function linkAttributes(mixed $link): string
    {
        if (! is_array($link)) {
            return '';
        }

        $external = ($link['is_external'] ?? null) === 'on';
        $rel = array_filter([$external ? 'noopener' : '', ($link['nofollow'] ?? null) === 'on' ? 'nofollow' : '']);

        return ($external ? ' target="_blank"' : '').($rel === [] ? '' : ' rel="'.implode(' ', $rel).'"');
    }

    /**
     * The URL of a link setting, or nothing when it could run script.
     */
    private function url(mixed $link): string
    {
        $url = is_array($link) ? $this->text($link, 'url') : '';

        return $url !== '' && SafeUrl::isSafe($url) ? $url : '';
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<array<array-key, mixed>>
     */
    private function items(array $settings, string $key): array
    {
        $items = $settings[$key] ?? null;

        return is_array($items) ? array_values(array_filter($items, is_array(...))) : [];
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function text(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        return is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
    }

    /**
     * @param  array<array-key, mixed>  $element
     * @return array<array-key, mixed>
     */
    private function settings(array $element): array
    {
        return is_array($element['settings'] ?? null) ? $element['settings'] : [];
    }

    private function number(mixed $value): string
    {
        return rtrim(rtrim(sprintf('%.3F', (float) $value), '0'), '.');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
