<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

/**
 * What the scan of one theme directory collected, before templates are
 * classified and a child theme is merged in.
 *
 * @internal
 */
final class ScanResult
{
    /** @var array<string, string> relative path => beginning of the file */
    public array $files = [];

    /** @var array<string, array{title: string, postTypes: list<string>}> */
    public array $customTemplates = [];

    /** @var array<string, list<string>> */
    public array $templateParts = [];

    /** @var array<string, PostTypeDefinition> */
    public array $postTypes = [];

    /** @var array<string, TaxonomyDefinition> */
    public array $taxonomies = [];

    /** @var array<string, ShortcodeDefinition> */
    public array $shortcodes = [];

    /** @var array<string, string> */
    public array $menuLocations = [];

    /** @var array<string, SidebarDefinition> */
    public array $sidebars = [];

    /** @var array<string, BlockTypeDefinition> */
    public array $blockTypes = [];

    /** @var array<string, AssetDefinition> */
    public array $scripts = [];

    /** @var array<string, AssetDefinition> */
    public array $styles = [];

    /** @var list<string> */
    public array $features = [];

    /** @var list<string> */
    public array $warnings = [];

    public function __construct(public readonly ThemeInfo $info) {}
}
