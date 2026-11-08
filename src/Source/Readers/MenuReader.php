<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Generator;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenu;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;

class MenuReader extends Reader
{
    public function count(): int
    {
        return $this->db->table('term_taxonomy')->where('taxonomy', 'nav_menu')->count();
    }

    /**
     * @return Generator<int, list<SourceMenu>>
     */
    public function chunks(): Generator
    {
        $locations = $this->locations();
        $taxonomies = $this->db->tableName('term_taxonomy');
        $terms = $this->db->tableName('terms');

        $rows = $this->db->table('term_taxonomy')
            ->join($terms, $terms.'.term_id', '=', $taxonomies.'.term_id')
            ->where($taxonomies.'.taxonomy', 'nav_menu')
            ->orderBy($terms.'.term_id')
            ->get([$terms.'.term_id', $terms.'.name', $terms.'.slug']);

        $menus = [];

        foreach ($rows as $row) {
            $id = self::int($row->term_id);
            $menus[] = new SourceMenu($id, self::string($row->name), rawurldecode(self::string($row->slug)), $locations[$id] ?? []);
        }

        foreach (array_chunk($menus, $this->chunkSize()) as $chunk) {
            yield $chunk;
        }
    }

    /**
     * The theme locations of the active theme, by menu.
     *
     * @return array<int, list<string>>
     */
    private function locations(): array
    {
        $theme = (string) $this->db->option('stylesheet');

        try {
            $mods = Serialized::decode((string) $this->db->option('theme_mods_'.$theme));
        } catch (MalformedSerializedValue) {
            return [];
        }

        $assigned = is_array($mods) && is_array($mods['nav_menu_locations'] ?? null) ? $mods['nav_menu_locations'] : [];
        $locations = [];

        foreach ($assigned as $location => $menu) {
            $menu = self::int($menu);

            if ($menu > 0) {
                $locations[$menu][] = (string) $location;
            }
        }

        return $locations;
    }
}
