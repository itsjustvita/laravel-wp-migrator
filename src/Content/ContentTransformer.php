<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content;

use ItsJustVita\WpMigrator\Content\Blocks\Block;
use ItsJustVita\WpMigrator\Content\Blocks\BlockParser;
use ItsJustVita\WpMigrator\Content\Blocks\BlockRenderer;
use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeContext;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeProcessor;
use ItsJustVita\WpMigrator\Content\Urls\UrlRewriter;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * Turns the raw content of a post into the stored block tree.
 *
 * URLs are rewritten first, while the text still holds only old URLs, so the
 * new URLs that shortcode handlers produce are not taken for old links.
 *
 * Content without block markers is classic content: its shortcodes are
 * rendered before it gets paragraphs, so their output counts as block-level
 * HTML. Block content already has its HTML, so it only gets URLs and
 * shortcodes, and references to reusable blocks are replaced by their content.
 */
class ContentTransformer
{
    /** Attributes that hold the ID of an attachment, by block name. */
    private const array MEDIA_ID_ATTRIBUTES = [
        'core/image' => 'id',
        'core/cover' => 'id',
        'core/video' => 'id',
        'core/audio' => 'id',
        'core/file' => 'id',
        'core/media-text' => 'mediaId',
    ];

    /** Blocks that show their text literally. */
    private const array LITERAL_BLOCKS = ['core/code'];

    private const string REUSABLE_BLOCK = 'core/block';

    /** @var array<int, list<Block>|null> */
    private array $reusableBlocks = [];

    public function __construct(
        private readonly BlockParser $parser,
        private readonly Autop $autop,
        private readonly ShortcodeProcessor $shortcodes,
        private readonly UrlRewriter $urls,
        private readonly BlockRenderer $renderer,
        private readonly WpMigrator $registry,
        private readonly WpDatabase $db,
    ) {}

    /**
     * @return list<Block>
     */
    public function transform(SourcePost $post, ImportContext $context): array
    {
        if (trim($post->content) === '') {
            return [];
        }

        $blocks = $this->parser->parse($post->content);

        if ($this->isClassicContent($blocks)) {
            return $this->transformClassic($post->content, $post->id, $context);
        }

        return $this->transformBlocks($blocks, $post->id, $context, []);
    }

    /**
     * @param  list<Block>  $blocks
     */
    private function isClassicContent(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (! $block->isFreeform()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<Block>
     */
    private function transformClassic(string $content, int $postId, ImportContext $context): array
    {
        $html = $this->urls->rewrite($content, $postId, $context);
        $html = $this->autop->format($this->renderShortcodes($html, $postId, $context));

        return $html === '' ? [] : [Block::freeform($html)];
    }

    /**
     * @param  list<Block>  $blocks
     * @param  array<int, true>  $expanding  reusable blocks being expanded, to stop cycles
     * @return list<Block>
     */
    private function transformBlocks(array $blocks, int $postId, ImportContext $context, array $expanding): array
    {
        $result = [];

        foreach ($blocks as $block) {
            if ($block->name === self::REUSABLE_BLOCK) {
                array_push($result, ...$this->expandReusable($block, $postId, $context, $expanding));

                continue;
            }

            $result[] = $this->transformBlock($block, $postId, $context, $expanding);
        }

        return $result;
    }

    /**
     * @param  array<int, true>  $expanding
     */
    private function transformBlock(Block $block, int $postId, ImportContext $context, array $expanding): Block
    {
        if (! $block->isFreeform() && ! $this->renderer->hasRenderer($block->name)) {
            $context->report->unknownBlock($block->name, $postId);
        }

        $attrs = $this->transformAttributes($block, $postId, $context);

        if ($block->innerContent === null) {
            return new Block($block->name, $attrs, $this->transformHtml($block, $block->innerHtml, $postId, $context));
        }

        $html = '';
        $innerBlocks = [];
        $innerContent = [];
        $next = 0;

        foreach ($block->innerContent as $part) {
            if ($part !== null) {
                $part = $this->transformHtml($block, $part, $postId, $context);
                $html .= $part;
                $innerContent[] = $part;

                continue;
            }

            $inner = isset($block->innerBlocks[$next]) ? [$block->innerBlocks[$next]] : [];
            $next++;

            foreach ($this->transformBlocks($inner, $postId, $context, $expanding) as $transformed) {
                $innerBlocks[] = $transformed;
                $innerContent[] = null;
            }
        }

        return new Block($block->name, $attrs, $html, $innerBlocks, $innerBlocks === [] ? null : $innerContent);
    }

    private function transformHtml(Block $block, string $html, int $postId, ImportContext $context): string
    {
        if (in_array($block->name, self::LITERAL_BLOCKS, true)) {
            return $html;
        }

        return $this->renderShortcodes($this->urls->rewrite($html, $postId, $context), $postId, $context);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function transformAttributes(Block $block, int $postId, ImportContext $context): array
    {
        $attrs = $this->rewriteValues($block->attrs, $postId, $context);

        $attribute = self::MEDIA_ID_ATTRIBUTES[$block->name] ?? null;

        if ($attribute !== null && is_numeric($attrs[$attribute] ?? null)) {
            $id = $this->mediaId((int) $attrs[$attribute], $postId, $context);

            if ($id === null) {
                unset($attrs[$attribute]);
            } else {
                $attrs[$attribute] = $id;
            }
        }

        if ($block->name === 'core/gallery' && is_array($attrs['ids'] ?? null)) {
            $attrs['ids'] = array_values(array_filter(array_map(
                fn (mixed $id): ?int => is_numeric($id) ? $this->mediaId((int) $id, $postId, $context) : null,
                $attrs['ids'],
            )));
        }

        return $attrs;
    }

    private function mediaId(int $sourceId, int $postId, ImportContext $context): ?int
    {
        return $context->resolve(SourceType::Post, $sourceId, $postId, 'block media', Media::class);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function rewriteValues(array $values, int $postId, ImportContext $context): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = $this->urls->rewrite($value, $postId, $context);
            } elseif (is_array($value)) {
                $values[$key] = $this->rewriteValues($value, $postId, $context);
            }
        }

        return $values;
    }

    /**
     * @param  array<int, true>  $expanding
     * @return list<Block>
     */
    private function expandReusable(Block $reference, int $postId, ImportContext $context, array $expanding): array
    {
        $ref = $reference->attr('ref');
        $ref = is_numeric($ref) ? (int) $ref : 0;

        if (isset($expanding[$ref])) {
            $context->warn($postId, sprintf('Reusable block %d refers to itself; the repetition was left out.', $ref));

            return [];
        }

        $blocks = $this->reusableBlocks($ref);

        if ($blocks === null) {
            $context->report->unresolved($context->importer, $postId, 'reusable block', $ref);

            return [];
        }

        return $this->transformBlocks($blocks, $postId, $context, $expanding + [$ref => true]);
    }

    /**
     * @return list<Block>|null
     */
    private function reusableBlocks(int $ref): ?array
    {
        if (array_key_exists($ref, $this->reusableBlocks)) {
            return $this->reusableBlocks[$ref];
        }

        $content = $this->db->table('posts')
            ->where('ID', $ref)
            ->where('post_type', 'wp_block')
            ->value('post_content');

        return $this->reusableBlocks[$ref] = $content === null ? null : $this->parser->parse((string) $content);
    }

    private function renderShortcodes(string $html, int $postId, ImportContext $context): string
    {
        return $this->shortcodes->process(
            $html,
            fn (string $tag): bool => $this->registry->hasShortcode($tag),
            function (Shortcode $shortcode) use ($postId, $context): string {
                $handler = $this->registry->shortcodeHandler($shortcode->tag);

                if ($handler === null) {
                    return '';
                }

                return $handler->render($shortcode, new ShortcodeContext(
                    $postId,
                    $context,
                    fn (string $content): string => $this->renderShortcodes($content, $postId, $context),
                ));
            },
            fn (string $tag) => $context->report->unknownShortcode($tag, $postId),
        );
    }
}
