<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Mirrors a typical application user model: the password cast re-hashes
 * anything assigned to it that does not already look like a hash.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $role
 */
final class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
