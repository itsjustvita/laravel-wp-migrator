<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

/**
 * Everything a static scan found in a theme and its child theme.
 */
final readonly class ThemeInventory
{
    /**
     * @param  list<Template>  $templates
     * @param  array<string, list<string>>  $templateParts  part => files that load it
     * @param  list<PostTypeDefinition>  $postTypes
     * @param  list<TaxonomyDefinition>  $taxonomies
     * @param  list<ShortcodeDefinition>  $shortcodes
     * @param  array<string, string>  $menuLocations  location => description
     * @param  list<SidebarDefinition>  $sidebars
     * @param  list<BlockTypeDefinition>  $blockTypes
     * @param  list<AssetDefinition>  $scripts
     * @param  list<AssetDefinition>  $styles
     * @param  list<string>  $features  arguments of add_theme_support
     * @param  list<string>  $warnings
     */
    public function __construct(
        public ThemeInfo $theme,
        public ?ThemeInfo $child,
        public bool $isBlockTheme,
        public array $templates,
        public array $templateParts,
        public array $postTypes,
        public array $taxonomies,
        public array $shortcodes,
        public array $menuLocations,
        public array $sidebars,
        public array $blockTypes,
        public array $scripts,
        public array $styles,
        public array $features,
        public array $warnings,
    ) {}

    public function template(string $path): ?Template
    {
        foreach ($this->templates as $template) {
            if ($template->path === $path) {
                return $template;
            }
        }

        return null;
    }
}
