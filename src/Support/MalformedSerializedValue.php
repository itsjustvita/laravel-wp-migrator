<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Support;

use RuntimeException;

final class MalformedSerializedValue extends RuntimeException
{
    public static function for(string $value): self
    {
        $preview = mb_strlen($value) > 80 ? mb_substr($value, 0, 77).'...' : $value;

        return new self(sprintf('Malformed serialized value: %s', $preview));
    }
}
