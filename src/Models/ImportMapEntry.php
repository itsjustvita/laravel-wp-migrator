<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Concerns\HasPrefixedTable;

/**
 * One row per imported source entity: where it went and what it looked like.
 *
 * @property int $id
 * @property SourceType $source_type
 * @property int $source_id
 * @property string $target_type
 * @property int $target_id
 * @property string $checksum empty when the row has to be processed again on the next run
 * @property bool $linked true when the target existed before and the import must not change it
 * @property Carbon $imported_at
 * @property-read Model|null $target
 */
class ImportMapEntry extends Model
{
    use HasPrefixedTable;

    protected $table = 'wp_import_map';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'source_id' => 'integer',
            'target_id' => 'integer',
            'linked' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
