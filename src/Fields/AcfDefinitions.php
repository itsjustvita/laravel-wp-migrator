<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Fields;

use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;

/**
 * The ACF field definitions stored in the source database, read once.
 * Field groups registered in PHP or kept only as local JSON are not in the
 * database, so their values are copied without conversion.
 */
class AcfDefinitions
{
    /** @var array<string, AcfField>|null */
    private ?array $byKey = null;

    /** @var array<int, list<AcfField>> */
    private array $children = [];

    public function __construct(private readonly WpDatabase $db) {}

    public function field(string $key): ?AcfField
    {
        return $this->load()[$key] ?? null;
    }

    /**
     * @return list<AcfField>
     */
    public function children(AcfField $field): array
    {
        $this->load();

        return $this->children[$field->id] ?? [];
    }

    /**
     * @return array<string, AcfField>
     */
    private function load(): array
    {
        if ($this->byKey !== null) {
            return $this->byKey;
        }

        $rows = $this->db->table('posts')
            ->whereIn('post_type', ['acf-field-group', 'acf-field'])
            ->where('post_status', '!=', 'trash')
            ->orderBy('menu_order')
            ->orderBy('ID')
            ->get(['ID', 'post_type', 'post_name', 'post_excerpt', 'post_content', 'post_parent']);

        $groups = [];

        foreach ($rows as $row) {
            if ($row->post_type === 'acf-field-group') {
                $groups[(int) $row->ID] = true;
            }
        }

        $this->byKey = [];

        foreach ($rows as $row) {
            if ($row->post_type !== 'acf-field') {
                continue;
            }

            try {
                $settings = Serialized::decode((string) $row->post_content);
            } catch (MalformedSerializedValue) {
                continue;
            }

            if (! is_array($settings) || ! is_string($settings['type'] ?? null)) {
                continue;
            }

            $field = new AcfField(
                id: (int) $row->ID,
                key: (string) $row->post_name,
                name: (string) $row->post_excerpt,
                type: $settings['type'],
                settings: $settings,
                parentId: (int) $row->post_parent,
                isTopLevel: isset($groups[(int) $row->post_parent]),
            );

            $this->byKey[$field->key] = $field;
            $this->children[$field->parentId][] = $field;
        }

        return $this->byKey;
    }
}
