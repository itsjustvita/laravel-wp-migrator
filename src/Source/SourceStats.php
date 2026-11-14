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

    /**
     * Users with the administrator role, oldest first. A hacked site can hold
     * an attacker's account among them.
     *
     * @return list<array{id: int, login: string, email: string, registered: string|null}>
     */
    public function administrators(): array
    {
        $users = $this->db->tableName('users');
        $meta = $this->db->tableName('usermeta');

        $query = $this->db->table('users')
            ->join($meta, $meta.'.user_id', '=', $users.'.ID')
            ->where($meta.'.meta_key', $this->db->prefix().'capabilities');

        $rows = Like::contains($query, $meta.'.meta_value', '"administrator"')
            ->orderBy($users.'.ID')
            ->get([$users.'.ID', $users.'.user_login', $users.'.user_email', $users.'.user_registered']);

        return array_values($rows->map(fn (object $row): array => [
            'id' => (int) $row->ID,
            'login' => (string) $row->user_login,
            'email' => (string) $row->user_email,
            'registered' => is_string($row->user_registered) && ! str_starts_with($row->user_registered, '0000') ? substr($row->user_registered, 0, 10) : null,
        ])->all());
    }

    public function mediaCount(): int
    {
        return $this->db->table('posts')->where('post_type', 'attachment')->count();
    }
}
