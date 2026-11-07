<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Fields;

/**
 * One ACF field definition, stored in WP as a post of type acf-field: the
 * key in post_name, the name in post_excerpt and the settings serialized in
 * post_content. Sub fields of repeaters, groups and flexible content have the
 * parent field's post as their parent.
 */
final readonly class AcfField
{
    /**
     * @param  array<array-key, mixed>  $settings
     */
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public string $type,
        public array $settings,
        public int $parentId,
        public bool $isTopLevel,
    ) {}

    /**
     * The key of the flexible content layout a sub field belongs to.
     */
    public function parentLayout(): ?string
    {
        $layout = $this->settings['parent_layout'] ?? null;

        return is_string($layout) ? $layout : null;
    }

    /**
     * The key of the flexible content layout with the given name.
     */
    public function layoutKey(string $name): ?string
    {
        $layouts = $this->settings['layouts'] ?? [];

        foreach (is_array($layouts) ? $layouts : [] as $key => $layout) {
            if (is_array($layout) && ($layout['name'] ?? null) === $name) {
                $layoutKey = $layout['key'] ?? $key;

                return is_scalar($layoutKey) ? (string) $layoutKey : null;
            }
        }

        return null;
    }
}
