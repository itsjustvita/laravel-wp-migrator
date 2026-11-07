<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Readers;

use Illuminate\Config\Repository;
use ItsJustVita\WpMigrator\Source\WpDatabase;

abstract class Reader
{
    public function __construct(
        protected readonly WpDatabase $db,
        protected readonly Repository $config,
        protected readonly MetaLoader $meta,
    ) {}

    /**
     * @return int<1, max>
     */
    protected function chunkSize(): int
    {
        return max(1, $this->config->integer('wp-migrator.import.chunk_size', 200));
    }

    protected static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    protected static function nullableString(mixed $value): ?string
    {
        $string = self::string($value);

        return $string === '' ? null : $string;
    }

    protected static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    protected static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * WP uses 0 for "no parent" and "no author".
     */
    protected static function idOrNull(mixed $value): ?int
    {
        $id = self::int($value);

        return $id > 0 ? $id : null;
    }
}
