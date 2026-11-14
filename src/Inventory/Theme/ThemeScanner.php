<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

use FilesystemIterator;
use PhpParser\Error;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Reads a theme without running it. PHP files are parsed into syntax trees
 * and searched for the calls that register post types, taxonomies,
 * shortcodes, menus, sidebars, blocks and assets; only values written
 * literally in the code can be read, everything else is reported.
 */
class ThemeScanner
{
    private const int MAX_FILE_SIZE = 1_000_000;

    private const int HEADER_BYTES = 8192;

    private const array SKIPPED_DIRECTORIES = ['vendor', 'node_modules', '.git', '.svn', '.idea'];

    private const array FUNCTIONS = [
        'register_post_type', 'register_taxonomy', 'add_shortcode', 'register_nav_menus', 'register_nav_menu',
        'register_sidebar', 'register_sidebars', 'register_block_type', 'register_block_type_from_metadata',
        'wp_enqueue_script', 'wp_register_script', 'wp_enqueue_style', 'wp_register_style',
        'get_template_part', 'add_theme_support',
    ];

    private ?Parser $parser = null;

    /**
     * @param  list<string>  $postTypes  post types known from elsewhere, such as the source database
     * @param  list<string>  $taxonomies  taxonomies known from elsewhere
     *
     * @throws InventoryException
     */
    public function scan(string $themePath, ?string $childPath = null, array $postTypes = [], array $taxonomies = []): ThemeInventory
    {
        $theme = $this->scanDirectory($themePath);
        $child = $childPath === null ? null : $this->scanDirectory($childPath);

        return $this->combine($theme, $child, $postTypes, $taxonomies);
    }

    private function scanDirectory(string $path): ScanResult
    {
        $root = realpath($path);

        if ($root === false || ! is_dir($root)) {
            throw new InventoryException(sprintf('The theme directory %s does not exist.', $path));
        }

        $result = new ScanResult($this->themeInfo($root));

        if (! is_file($root.'/style.css')) {
            $result->warnings[] = sprintf('%s has no style.css with a Theme Name header; check that it is a theme.', $root);
        }

        foreach ($this->files($root, $result) as $relative => $file) {
            $extension = strtolower($file->getExtension());

            if ($file->getSize() > self::MAX_FILE_SIZE) {
                $result->warnings[] = sprintf('Skipped %s: larger than 1 MB.', $relative);

                continue;
            }

            match (true) {
                $extension === 'php' => $this->scanPhp($root, $relative, $result),
                $extension === 'html' => $result->files[$relative] = '',
                basename($relative) === 'block.json' => $this->scanBlockJson($root, $relative, $result),
                $relative === 'theme.json' => $this->scanThemeJson($root, $result),
                default => null,
            };
        }

        return $result;
    }

    /**
     * Theme files sorted by path. Bundled dependencies are left out, and so
     * is anything a symbolic link leads to outside the theme.
     *
     * @return array<string, SplFileInfo>
     */
    private function files(string $root, ScanResult $result): array
    {
        $directories = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator($directories, function (SplFileInfo $file): bool {
            return ! ($file->isDir() && in_array($file->getFilename(), self::SKIPPED_DIRECTORIES, true));
        });

        $files = [];

        foreach (new RecursiveIteratorIterator($filtered) as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $real = $file->getRealPath();

            if ($real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $files[str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1))] = $file;
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    private function themeInfo(string $root): ThemeInfo
    {
        $header = is_file($root.'/style.css') ? (string) file_get_contents($root.'/style.css', length: self::HEADER_BYTES) : '';
        $field = function (string $name) use ($header): ?string {
            return preg_match('/^[ \t\/*#@]*'.preg_quote($name, '/').':(.*)$/mi', $header, $matches) === 1 && trim($matches[1]) !== ''
                ? trim($matches[1])
                : null;
        };

        return new ThemeInfo(
            slug: basename($root),
            path: $root,
            name: $field('Theme Name') ?? basename($root),
            version: $field('Version'),
            textDomain: $field('Text Domain'),
            parent: $field('Template'),
        );
    }

    private function scanPhp(string $root, string $relative, ScanResult $result): void
    {
        $code = (string) file_get_contents($root.'/'.$relative);
        $result->files[$relative] = substr($code, 0, self::HEADER_BYTES);

        try {
            $ast = $this->parser()->parse($code) ?? [];
        } catch (Error $error) {
            $result->warnings[] = sprintf('Could not read %s: %s.', $relative, $error->getMessage());

            return;
        }

        $collector = new CallCollector(self::FUNCTIONS);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        $directory = dirname($relative);
        $evaluator = new Evaluator($directory === '.' ? '' : $directory);

        foreach ($collector->calls as $call) {
            $this->record($call, $evaluator, $root, $relative, $result);
        }
    }

    private function record(FuncCall $call, Evaluator $evaluator, string $root, string $file, ScanResult $result): void
    {
        $name = strtolower($call->name instanceof Name ? $call->name->toString() : '');
        $arguments = array_map(fn (Arg $argument): Expr => $argument->value, $call->getArgs());
        $first = $evaluator->string($arguments[0] ?? null);

        $needsName = ['register_post_type', 'register_taxonomy', 'add_shortcode', 'register_nav_menu', 'wp_enqueue_script', 'wp_register_script', 'wp_enqueue_style', 'wp_register_style', 'get_template_part', 'add_theme_support', 'register_block_type', 'register_block_type_from_metadata'];

        if ($first === null && in_array($name, $needsName, true)) {
            $result->warnings[] = sprintf('%s in %s on line %d uses a value that is only known at runtime; check it by hand.', $name, $file, $call->getStartLine());

            return;
        }

        $first = (string) $first;

        match ($name) {
            'register_post_type' => $result->postTypes[$first] = $this->postType($first, $this->arrayValue($evaluator, $arguments[1] ?? null), $file),
            'register_taxonomy' => $result->taxonomies[$first] = $this->taxonomy($first, $evaluator->value($arguments[1] ?? null), $this->arrayValue($evaluator, $arguments[2] ?? null), $file),
            'add_shortcode' => $result->shortcodes[$first] = new ShortcodeDefinition($first, $this->callback($arguments[1] ?? null), $file),
            'register_nav_menus' => $this->menuLocations($this->arrayValue($evaluator, $arguments[0] ?? null), $result),
            'register_nav_menu' => $result->menuLocations[$first] = $evaluator->string($arguments[1] ?? null) ?? $first,
            'register_sidebar', 'register_sidebars' => $this->sidebar($this->arrayValue($evaluator, $arguments[$name === 'register_sidebar' ? 0 : 1] ?? null), $file, $result),
            'register_block_type', 'register_block_type_from_metadata' => $this->blockType($first, $root, $file, $result),
            'wp_enqueue_script', 'wp_register_script' => $this->asset($result->scripts, $first, $evaluator, $arguments, $file),
            'wp_enqueue_style', 'wp_register_style' => $this->asset($result->styles, $first, $evaluator, $arguments, $file),
            'get_template_part' => $this->templatePart($first, $evaluator->value($arguments[1] ?? null), $file, $result),
            'add_theme_support' => $result->features = array_values(array_unique([...$result->features, $first])),
            default => null,
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayValue(Evaluator $evaluator, ?Expr $expr): array
    {
        $value = $evaluator->value($expr);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $args
     */
    private function postType(string $name, array $args, string $file): PostTypeDefinition
    {
        $labels = is_array($args['labels'] ?? null) ? $args['labels'] : [];
        $rewrite = $args['rewrite'] ?? null;
        $archive = $args['has_archive'] ?? null;

        return new PostTypeDefinition(
            name: $name,
            label: $this->stringOrNull($labels['name'] ?? $args['label'] ?? null),
            public: $this->boolOrNull($args['public'] ?? null),
            hasArchive: is_bool($archive) || is_string($archive) ? $archive : null,
            hierarchical: $this->boolOrNull($args['hierarchical'] ?? null),
            rewriteSlug: is_array($rewrite) ? $this->stringOrNull($rewrite['slug'] ?? null) : null,
            supports: $this->strings($args['supports'] ?? []),
            file: $file,
        );
    }

    /**
     * @param  array<array-key, mixed>  $args
     */
    private function taxonomy(string $name, mixed $objectTypes, array $args, string $file): TaxonomyDefinition
    {
        $rewrite = $args['rewrite'] ?? null;
        $labels = is_array($args['labels'] ?? null) ? $args['labels'] : [];

        return new TaxonomyDefinition(
            name: $name,
            label: $this->stringOrNull($labels['name'] ?? $args['label'] ?? null),
            postTypes: is_string($objectTypes) ? [$objectTypes] : $this->strings($objectTypes),
            hierarchical: $this->boolOrNull($args['hierarchical'] ?? null),
            rewriteSlug: is_array($rewrite) ? $this->stringOrNull($rewrite['slug'] ?? null) : null,
            file: $file,
        );
    }

    private function callback(?Expr $expr): string
    {
        return match (true) {
            $expr instanceof Expr && (new Evaluator)->string($expr) !== null => (string) (new Evaluator)->string($expr),
            $expr instanceof Closure, $expr instanceof ArrowFunction => 'a closure',
            $expr instanceof Array_ => 'a method',
            default => 'a callback',
        };
    }

    /**
     * @param  array<array-key, mixed>  $locations
     */
    private function menuLocations(array $locations, ScanResult $result): void
    {
        foreach ($locations as $location => $description) {
            if (is_string($location)) {
                $result->menuLocations[$location] = is_string($description) ? $description : $location;
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $args
     */
    private function sidebar(array $args, string $file, ScanResult $result): void
    {
        $name = $this->stringOrNull($args['name'] ?? null);
        $id = $this->stringOrNull($args['id'] ?? null);

        if ($id === null && $name === null) {
            $result->warnings[] = sprintf('A sidebar in %s has neither a literal name nor an ID.', $file);

            return;
        }

        if ($id === null) {
            // WP numbers sidebars without an ID in the order they are registered.
            $id = 'sidebar-'.(count($result->sidebars) + 1);
            $result->warnings[] = sprintf('The sidebar "%s" in %s has no ID; WP numbers it by registration order, so "%s" is a guess.', $name, $file, $id);
        }

        $result->sidebars[$id] = new SidebarDefinition($id, $name ?? $id, $file);
    }

    /**
     * A block type is registered by its name or by the directory of its block.json.
     */
    private function blockType(string $argument, string $root, string $file, ScanResult $result): void
    {
        if (preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $argument) === 1) {
            $result->blockTypes[$argument] ??= new BlockTypeDefinition($argument, $file);

            return;
        }

        $relative = trim((string) preg_replace('/^\{(theme|stylesheet)-dir\}/', '', $argument), '/');
        $relative = str_ends_with($relative, 'block.json') ? $relative : ltrim($relative.'/block.json', '/');
        $real = realpath($root.'/'.$relative);

        if ($real === false || ! is_file($real) || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            $result->warnings[] = sprintf('register_block_type in %s points to %s, which has no block.json in the theme.', $file, $argument);

            return;
        }

        $this->scanBlockJson($root, str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root) + 1)), $result);
    }

    private function scanBlockJson(string $root, string $relative, ScanResult $result): void
    {
        $json = json_decode((string) file_get_contents($root.'/'.$relative), true);
        $name = is_array($json) ? ($json['name'] ?? null) : null;

        if (is_string($name) && $name !== '') {
            $result->blockTypes[$name] ??= new BlockTypeDefinition($name, $relative);
        }
    }

    private function scanThemeJson(string $root, ScanResult $result): void
    {
        $json = json_decode((string) file_get_contents($root.'/theme.json'), true);
        $templates = is_array($json) && is_array($json['customTemplates'] ?? null) ? $json['customTemplates'] : [];

        foreach ($templates as $template) {
            if (is_array($template) && is_string($template['name'] ?? null)) {
                $result->customTemplates[$template['name']] = [
                    'title' => is_string($template['title'] ?? null) ? $template['title'] : $template['name'],
                    'postTypes' => $this->strings($template['postTypes'] ?? ['page']),
                ];
            }
        }
    }

    /**
     * @param  array<string, AssetDefinition>  $assets
     * @param  array<int, Expr>  $arguments
     */
    private function asset(array &$assets, string $handle, Evaluator $evaluator, array $arguments, string $file): void
    {
        $source = isset($arguments[1]) ? $evaluator->string($arguments[1]) : null;

        if (isset($assets[$handle]) && $source === null) {
            return;
        }

        $assets[$handle] = new AssetDefinition($handle, $source, $this->strings($evaluator->value($arguments[2] ?? null)), $file);
    }

    private function templatePart(string $slug, mixed $name, string $file, ScanResult $result): void
    {
        $part = is_string($name) && $name !== '' ? $slug.'-'.$name : $slug;

        if (! in_array($file, $result->templateParts[$part] ?? [], true)) {
            $result->templateParts[$part][] = $file;
        }
    }

    /**
     * @param  list<string>  $postTypes
     * @param  list<string>  $taxonomies
     */
    private function combine(ScanResult $theme, ?ScanResult $child, array $postTypes, array $taxonomies): ThemeInventory
    {
        $results = $child === null ? [$theme] : [$theme, $child];
        $merge = fn (string $property): array => array_merge(...array_map(fn (ScanResult $result): array => $result->{$property}, $results));

        $hierarchy = new TemplateHierarchy(
            array_values(array_unique(['post', 'page', ...$postTypes, ...array_keys($merge('postTypes'))])),
            array_values(array_unique(['category', 'post_tag', ...$taxonomies, ...array_keys($merge('taxonomies'))])),
        );

        $templates = [];

        foreach ($results as $index => $result) {
            foreach ($result->files as $path => $head) {
                $template = $hierarchy->classify($path, $head, $result->customTemplates);

                if ($template !== null) {
                    $templates[$path] = $template->withSource($index === 0 ? 'theme' : 'child');
                }
            }
        }

        ksort($templates, SORT_STRING);

        $parts = [];

        foreach ($results as $result) {
            foreach ($result->templateParts as $part => $files) {
                $parts[$part] = array_values(array_unique([...$parts[$part] ?? [], ...$files]));
                sort($parts[$part]);
            }
        }

        $warnings = $theme->warnings;

        foreach ($child->warnings ?? [] as $warning) {
            $warnings[] = 'Child theme: '.$warning;
        }

        return new ThemeInventory(
            theme: $theme->info,
            child: $child?->info,
            isBlockTheme: isset($theme->files['templates/index.html']) || isset($child->files['templates/index.html']),
            templates: array_values($templates),
            templateParts: $parts,
            postTypes: array_values($merge('postTypes')),
            taxonomies: array_values($merge('taxonomies')),
            shortcodes: array_values($merge('shortcodes')),
            menuLocations: $merge('menuLocations'),
            sidebars: array_values($merge('sidebars')),
            blockTypes: array_values($merge('blockTypes')),
            scripts: array_values($merge('scripts')),
            styles: array_values($merge('styles')),
            features: array_values(array_unique($merge('features'))),
            warnings: $warnings,
        );
    }

    private function parser(): Parser
    {
        return $this->parser ??= (new ParserFactory)->createForNewestSupportedVersion();
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function boolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
