<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;

/**
 * The meta rows of one object. WP allows several rows per key, so every
 * key maps to a list of raw string values.
 */
final readonly class MetaBag
{
    /**
     * @param  array<string, list<string>>  $values
     */
    public function __construct(private array $values = []) {}

    public static function empty(): self
    {
        return new self;
    }

    public function get(string $key): ?string
    {
        return $this->values[$key][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function all(string $key): array
    {
        return $this->values[$key] ?? [];
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    /**
     * The first value of the key, unserialized when it is serialized PHP.
     *
     * @throws MalformedSerializedValue
     */
    public function decode(string $key): mixed
    {
        $value = $this->get($key);

        return $value === null ? null : Serialized::decode($value);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->values);
    }

    /**
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
