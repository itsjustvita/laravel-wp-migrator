<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Generator;
use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourceUser;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\WpDates;
use stdClass;

class UserReader extends Reader
{
    public function count(UserFilter $filter): int
    {
        return $this->query($filter)->count();
    }

    /**
     * @return Generator<int, list<SourceUser>>
     */
    public function chunks(UserFilter $filter): Generator
    {
        $size = $this->chunkSize();
        $capabilitiesKey = $this->db->prefix().'capabilities';

        foreach ($this->query($filter)->lazyById($size, 'ID')->chunk($size) as $rows) {
            $ids = array_values($rows->map(fn (object $row): int => self::int($row->ID))->all());
            $meta = $this->meta->load('usermeta', 'user_id', $ids, 'umeta_id');

            $users = [];

            foreach ($rows as $row) {
                $id = self::int($row->ID);
                $users[] = $this->toUser($row, $meta[$id] ?? MetaBag::empty(), $capabilitiesKey);
            }

            yield $users;
        }
    }

    private function query(UserFilter $filter): Builder
    {
        return $this->db->table('users');
    }

    private function toUser(stdClass $row, MetaBag $meta, string $capabilitiesKey): SourceUser
    {
        $roles = [];
        $malformed = false;

        try {
            $capabilities = $meta->decode($capabilitiesKey);

            foreach (is_array($capabilities) ? $capabilities : [] as $role => $granted) {
                if ($granted) {
                    $roles[] = (string) $role;
                }
            }
        } catch (MalformedSerializedValue) {
            $malformed = true;
        }

        return new SourceUser(
            id: self::int($row->ID),
            login: self::string($row->user_login),
            email: self::string($row->user_email),
            displayName: self::string($row->display_name),
            nicename: self::string($row->user_nicename),
            url: self::nullableString($row->user_url),
            passwordHash: self::string($row->user_pass),
            registeredAt: WpDates::parse(self::nullableString($row->user_registered), null),
            roles: $roles,
            rolesMalformed: $malformed,
            meta: $meta,
        );
    }
}
