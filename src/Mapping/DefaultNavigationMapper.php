<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Blocks\BlockParser;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\Concerns\UniqueMenuSlug;
use ItsJustVita\WpMigrator\Menus\MenuLink;
use ItsJustVita\WpMigrator\Menus\MenuLinks;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;

/**
 * Block themes keep their menus as wp_navigation posts made of navigation
 * blocks. Each becomes a menu; its items are written anew on every change,
 * because the blocks have no IDs of their own.
 */
class DefaultNavigationMapper implements NavigationMapper
{
    use UniqueMenuSlug;

    private const array LINK_BLOCKS = ['core/navigation-link', 'core/navigation-submenu'];

    public function __construct(
        protected readonly BlockParser $parser,
        protected readonly MenuLinks $links,
    ) {}

    public function map(SourcePost $navigation, ImportContext $context): ?Model
    {
        $menu = $context->existing(SourceType::Post, $navigation->id, Menu::class) ?? new Menu;
        $slug = $navigation->slug !== '' ? $navigation->slug : Str::slug($navigation->title);

        $menu->fill([
            'name' => $navigation->title !== '' ? $navigation->title : 'Navigation '.$navigation->id,
            'slug' => $this->uniqueSlug($menu, $slug !== '' ? $slug : 'navigation', $navigation->id),
            'locations' => null,
        ])->save();

        $menu->items()->delete();

        $order = 0;
        $leftOut = [];
        $this->createItems($this->parser->parse($navigation->content), $menu, null, $navigation, $context, $order, $leftOut);

        if ($leftOut !== []) {
            $context->warn($navigation->id, sprintf(
                'Navigation menu "%s" contains blocks that have no menu item equivalent and were left out: %s.',
                $menu->name,
                implode(', ', array_keys($leftOut)),
            ));
        }

        return $menu;
    }

    /**
     * @param  list<Block>  $blocks
     * @param  array<string, true>  $leftOut
     */
    private function createItems(array $blocks, Menu $menu, ?MenuItem $parent, SourcePost $navigation, ImportContext $context, int &$order, array &$leftOut): void
    {
        foreach ($blocks as $block) {
            if ($block->isFreeform()) {
                if (trim(strip_tags($block->innerHtml)) !== '') {
                    $leftOut['HTML'] = true;
                }

                continue;
            }

            if ($block->name === 'core/home-link') {
                $label = $block->attr('label');
                $this->createItem($menu, $parent, is_string($label) && $label !== '' ? $label : 'Home', new MenuLink('/'), $block, $order);

                continue;
            }

            if (! in_array($block->name, self::LINK_BLOCKS, true)) {
                $leftOut[$block->name] = true;

                continue;
            }

            $label = $block->attr('label');
            $label = is_string($label) ? $label : '';
            $link = $this->link($block, $navigation->id, $context);

            if ($link->unpublished && $block->innerBlocks === []) {
                $context->warn($navigation->id, sprintf('The navigation link "%s" points to content that is not published and was left out.', $label));

                continue;
            }

            $item = $this->createItem($menu, $parent, $label, $link->unpublished ? new MenuLink(null) : $link, $block, $order);

            $this->createItems($block->innerBlocks, $menu, $item, $navigation, $context, $order, $leftOut);
        }
    }

    private function createItem(Menu $menu, ?MenuItem $parent, string $title, MenuLink $link, Block $block, int &$order): MenuItem
    {
        $className = $block->attr('className');
        $classes = is_string($className) ? array_values(array_filter(preg_split('/\s+/', $className) ?: [])) : [];

        return $menu->items()->create([
            'parent_id' => $parent?->id,
            'title' => $title,
            'order' => $order++,
            'target' => $block->attr('opensInNewTab') === true ? '_blank' : null,
            'classes' => $classes === [] ? null : $classes,
            ...$link->attributes(),
        ]);
    }

    private function link(Block $block, int $navigationId, ImportContext $context): MenuLink
    {
        $id = $block->attr('id');
        $kind = $block->attr('kind');
        $url = $block->attr('url');
        $url = is_string($url) ? $url : '';

        if (is_int($id) && $kind === 'post-type') {
            $link = $this->links->post($id, $navigationId, $context);

            return $link->url !== null || $link->unpublished ? $link : $this->links->custom($url, $navigationId, $context);
        }

        if (is_int($id) && $kind === 'taxonomy') {
            $link = $this->links->term($id, $navigationId, $context);

            return $link->url !== null ? $link : $this->links->custom($url, $navigationId, $context);
        }

        return $this->links->custom($url, $navigationId, $context);
    }
}
