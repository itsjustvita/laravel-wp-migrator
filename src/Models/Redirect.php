<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use ItsJustVita\WpMigrator\Models\Concerns\HasPrefixedTable;

/**
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property int $hits
 * @property Carbon|null $last_hit_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Redirect extends Model
{
    use HasPrefixedTable;

    protected $table = 'redirects';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }
}
