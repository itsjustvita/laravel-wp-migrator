<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use ItsJustVita\WpMigrator\Content\Blocks\BlockRenderer;

/**
 * <x-wp-blocks :blocks="$post->blocks" />
 */
class Blocks extends Component
{
    /**
     * @param  iterable<mixed>|null  $blocks
     */
    public function __construct(
        public ?iterable $blocks = null,
    ) {}

    /**
     * An Htmlable, because a plain string returned from here would be compiled
     * as a Blade template, and post content must never be executed.
     */
    public function render(): Htmlable
    {
        return new HtmlString(app(BlockRenderer::class)->renderMany($this->blocks));
    }
}
