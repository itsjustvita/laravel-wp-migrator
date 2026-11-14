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

        $numbers = [];
        $names = [];

        foreach ($entries as $entry) {
            if (is_int($entry) || ctype_digit($entry)) {
                $numbers[] = (int) $entry;
            }

            if (is_string($entry)) {
                $names[] = $entry;
            }
        }

        $connection = $this->db->connection();

        $rows = $this->db->table('users')
            ->where(function (Builder $query) use ($numbers, $names, $connection): void {
                $query->whereIn('ID', $numbers)
                    ->orWhereIn($connection->raw('LOWER(user_login)'), $names)
                    ->orWhereIn($connection->raw('LOWER(user_email)'), $names);
            })
            ->orderBy('ID')
            ->get(['ID', 'user_login', 'user_email']);

        $ids = [];
        $unmatched = [];

        foreach ($entries as $entry) {
            $matched = false;

            foreach ($rows as $row) {
                $byId = (is_int($entry) || ctype_digit($entry)) && (int) $row->ID === (int) $entry;
                $byName = in_array($entry, [mb_strtolower((string) $row->user_login), mb_strtolower((string) $row->user_email)], true);

                if ($byId || $byName) {
                    $ids[(int) $row->ID] = true;
                    $matched = true;
                }
            }

            if (! $matched) {
                $unmatched[] = (string) $entry;
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return $this->resolved = ['ids' => $ids, 'unmatched' => $unmatched];
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
