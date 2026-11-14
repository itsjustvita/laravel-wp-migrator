<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source;

use Illuminate\Config\Repository;
use Illuminate\Database\Query\Builder;

/**
 * The WP users that users.excluded leaves out of the import, given by ID,
 * login or email address. A compromised site can hold an attacker's account,
 * which would otherwise be imported with a working password.
 *
 * An integer is an ID. A string is a login or an email address, and a string
 * of digits is also tried as an ID, since logins can be numbers too: when in
 * doubt, more users are left out, never fewer.
 */
class ExcludedUsers
{
    /** @var array{ids: list<int>, unmatched: list<string>}|null */
    private ?array $resolved = null;

    public function __construct(
        private readonly WpDatabase $db,
        private readonly Repository $config,
    ) {}

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return $this->resolve()['ids'];
    }

    /**
     * The configured entries that match no user, such as a misspelt login.
     *
     * @return list<string>
     */
    public function unmatched(): array
    {
        return $this->resolve()['unmatched'];
    }

    /**
     * @return array{ids: list<int>, unmatched: list<string>}
     */
    private function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $entries = $this->entries();

        if ($entries === []) {
            return $this->resolved = ['ids' => [], 'unmatched' => []];
        }

        $ids = [];
        $unmatched = [];

        // One query per entry, and every row it returns is left out: the
        // database alone decides what matches, under its own collation.
        foreach ($entries as $entry) {
            $found = $this->matching($entry);

            foreach ($found as $id) {
                $ids[$id] = true;
            }

            if ($found === []) {
                $unmatched[] = (string) $entry;
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return $this->resolved = ['ids' => $ids, 'unmatched' => $unmatched];
    }

    /**
     * The users an entry names: an integer by ID, a string by login or email
     * address, and a string of digits by ID as well.
     *
     * @return list<int>
     */
    private function matching(int|string $entry): array
    {
        $connection = $this->db->connection();

        $query = $this->db->table('users')->where(function (Builder $query) use ($entry, $connection): void {
            if (is_int($entry) || ctype_digit($entry)) {
                $query->orWhere('ID', (int) $entry);
            }

            if (is_string($entry)) {
                $query->orWhere($connection->raw('LOWER(user_login)'), $entry)
                    ->orWhere($connection->raw('LOWER(user_email)'), $entry);
            }
        });

        return array_values(array_map(intval(...), $query->pluck('ID')->all()));
    }

    /**
     * The configured entries: integers as they are, strings trimmed and in
     * lower case, empty and other values left out.
     *
     * @return list<int|string>
     */
    private function entries(): array
    {
        $configured = $this->config->get('wp-migrator.users.excluded', []);
        $entries = [];

        foreach (is_array($configured) ? $configured : [] as $entry) {
            if (is_int($entry)) {
                $entries[] = $entry;
            } elseif (is_string($entry) && trim($entry) !== '') {
                $entries[] = mb_strtolower(trim($entry));
            }
        }

        return $entries;
    }
}
