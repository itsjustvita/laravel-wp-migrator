<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Tests\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * An application model a custom post type is mapped onto in tests.
 *
 * @property int $id
 * @property string $title
 * @property string|null $starts_at
 */
final class Event extends Model
{
    protected $table = 'events';

    protected $guarded = [];
}
