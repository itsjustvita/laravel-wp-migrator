<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory;

use ItsJustVita\WpMigrator\Inventory\Theme\Template;

/**
 * One part of the new frontend: a route with its view, or a layout.
 *
 * kind is one of: home, single, archive, specific (a page or post with a
 * view of its own), layout (for a page template), term, author, search and
 * not-found.
 */
final readonly class FrontendItem
{
    /**
     * @param  string|null  $subject  the post type or taxonomy; for a layout, the value stored in posts.template
     * @param  string|null  $uri  the route, or null without a URL pattern
     * @param  string  $view  the view name, such as "events.show"
     * @param  Template|null  $template  the theme file it replaces
     * @param  string|null  $slug  the page or post a specific view is for
     */
    public function __construct(
        public string $kind,
        public string $label,
        public ?string $subject,
        public ?string $uri,
        public string $view,
        public ?Template $template = null,
        public ?string $slug = null,
    ) {}

    /**
     * The file of the view, relative to the application.
     */
    public function viewPath(): string
    {
        return 'resources/views/'.str_replace('.', '/', $this->view).'.blade.php';
    }
}
