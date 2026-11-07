<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Blocks;

use Illuminate\View\Factory;
use ItsJustVita\WpMigrator\WpMigrator;

/**
 * Renders a block tree to HTML. A block is rendered by the view registered for
 * its name, by its view in wp-migrator::blocks, or else from its stored HTML.
 *
 * Views receive $block, $attrs and $content, which is the block's stored HTML
 * with its inner blocks already rendered in place. Stored HTML is output as it
 * is: it comes from the old site and counts as trusted, like a post in WP.
 */
class BlockRenderer
{
    private const string VALID_NAME = '/^[a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?$/';

    /** @var array<string, string|null> */
    private array $views = [];

    public function __construct(
        private readonly Factory $factory,
        private readonly WpMigrator $registry,
    ) {}

    public function hasRenderer(string $name): bool
    {
        return $this->viewFor($name) !== null;
    }

    /**
     * @param  iterable<mixed>|null  $blocks  Block objects or their stored array form
     */
    public function renderMany(?iterable $blocks): string
    {
        $html = '';

        foreach ($blocks ?? [] as $block) {
            if (is_array($block)) {
                $block = Block::fromArray($block);
            }

            if ($block instanceof Block) {
                $html .= $this->render($block);
            }
        }

        return $html;
    }

    public function render(Block $block): string
    {
        $content = $this->content($block);
        $view = $this->viewFor($block->name);

        if ($view === null) {
            return $content;
        }

        return $this->factory->first([$view], [
            'block' => $block,
            'attrs' => $block->attrs,
            'content' => $content,
        ])->render();
    }

    /**
     * The block's HTML with its inner blocks rendered in place.
     */
    private function content(Block $block): string
    {
        if ($block->innerContent === null) {
            return $block->innerHtml.$this->renderMany($block->innerBlocks);
        }

        $html = '';
        $inner = 0;

        foreach ($block->innerContent as $part) {
            if ($part !== null) {
                $html .= $part;
            } elseif (isset($block->innerBlocks[$inner])) {
                $html .= $this->render($block->innerBlocks[$inner++]);
            }
        }

        return $html;
    }

    private function viewFor(string $name): ?string
    {
        if (array_key_exists($name, $this->views)) {
            return $this->views[$name];
        }

        return $this->views[$name] = $this->resolveView($name);
    }

    private function resolveView(string $name): ?string
    {
        $registered = $this->registry->blockView($name);

        if ($registered !== null) {
            return $registered;
        }

        if (preg_match(self::VALID_NAME, $name) !== 1) {
            return null;
        }

        $path = str_starts_with($name, 'core/') ? substr($name, 5) : str_replace('/', '.', $name);
        $view = 'wp-migrator::blocks.'.$path;

        return $this->factory->exists($view) ? $view : null;
    }
}
