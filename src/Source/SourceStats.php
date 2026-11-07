<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source;

/**
 * Row counts of the source site, as shown by wp:check.
 */
class SourceStats
{
    public function __construct(private readonly WpDatabase $db) {}

    /**
     * Content posts grouped by type and status, largest type first.
     *
     * @return array<string, array<string, int>>
     */
    public function postCounts(): array
    {
        $rows = $this->db->posts()
            ->selectRaw('post_type, post_status, count(*) as total')
            ->groupBy('post_type', 'post_status')
            ->orderBy('post_type')
            ->orderBy('post_status')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->post_type][(string) $row->post_status] = (int) $row->total;
        }

        foreach ($counts as &$statuses) {
            uksort($statuses, function (string $a, string $b) use ($statuses): int {
                return $statuses[$b] <=> $statuses[$a] ?: strcmp($a, $b);
            });
        }
        unset($statuses);

        uksort($counts, function (string $a, string $b) use ($counts): int {
            return array_sum($counts[$b]) <=> array_sum($counts[$a]) ?: strcmp($a, $b);
        });

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function taxonomyCounts(): array
    {
        $rows = $this->db->table('term_taxonomy')
            ->selectRaw('taxonomy, count(*) as total')
            ->groupBy('taxonomy')
            ->orderByDesc('total')
            ->orderBy('taxonomy')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->taxonomy] = (int) $row->total;
        }

        return $counts;
    }

    public function userCount(): int
    {
        return $this->db->table('users')->count();
    }

    public function mediaCount(): int
    {
        return $this->db->table('posts')->where('post_type', 'attachment')->count();
    }
}
