<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Support\WpDates;

/**
 * Saves a model with the creation and modification dates of its source row
 * instead of the time of the import.
 */
trait StoresSourceTimestamps
{
    protected function saveWithSourceTimestamps(Model $model, ?DateTimeInterface $createdAt, ?DateTimeInterface $updatedAt): void
    {
        if (! $model->usesTimestamps()) {
            $model->save();

            return;
        }

        $createdAt = WpDates::forStorage($createdAt ?? $updatedAt ?? now());
        $updatedAt = WpDates::forStorage($updatedAt) ?? $createdAt;

        $createdAtColumn = $model->getCreatedAtColumn();
        $updatedAtColumn = $model->getUpdatedAtColumn();

        if ($createdAtColumn !== null) {
            $model->setAttribute($createdAtColumn, $createdAt);
        }

        if ($updatedAtColumn !== null) {
            $model->setAttribute($updatedAtColumn, $updatedAt);
        }

        $model::withoutTimestamps(fn (): bool => $model->save());
    }
}
