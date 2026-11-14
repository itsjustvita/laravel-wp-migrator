<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source;

use Illuminate\Config\Repository;
use Illuminate\Database\Query\Builder;

/**
 * The WP users that users.excluded leaves out of the import, given by ID,
 * login or email address. A compromised site can hold an attacker's account,
 * which would otherwise be imported with a working password.
 */
class ExcludedUsers
{
    /** @var list<int>|null */
    private ?array $ids = null;

    public function __construct(
        private readonly WpDatabase $db,
        private readonly Repository $config,
    ) {}

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        if ($this->ids !== null) {
            return $this->ids;
        }

        $entries = $this->config->get('wp-migrator.users.excluded', []);
        $numbers = [];
        $names = [];

        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (is_int($entry) || (is_string($entry) && ctype_digit($entry))) {
                $numbers[] = (int) $entry;
            } elseif (is_string($entry) && trim($entry) !== '') {
                $names[] = mb_strtolower(trim($entry));
            }
        }

        if ($numbers === [] && $names === []) {
            return $this->ids = [];
        }

        $connection = $this->db->connection();

        $ids = $this->db->table('users')
            ->where(function (Builder $query) use ($numbers, $names, $connection): void {
                $query->whereIn('ID', $numbers)
                    ->orWhereIn($connection->raw('LOWER(user_login)'), $names)
                    ->orWhereIn($connection->raw('LOWER(user_email)'), $names);
            })
            ->orderBy('ID')
            ->pluck('ID');

        return $this->ids = array_values(array_map(intval(...), $ids->all()));
    }
}
