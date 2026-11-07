<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Support;

/**
 * Reads PHP-serialized values the way WP stores them in meta and options,
 * without ever instantiating classes.
 */
final class Serialized
{
    /**
     * Scalars must be complete so ordinary text such as "s:5 of 10" does not
     * match. Arrays and objects only need their opening, because a truncated
     * value is exactly the damage that has to be reported as malformed.
     */
    public static function looksSerialized(string $value): bool
    {
        return preg_match('/^(?:N;|b:[01];|i:-?\d+;|d:[^;]+;|s:\d+:".*";|a:\d+:\{|O:\d+:")/s', $value) === 1;
    }

    /**
     * Returns the decoded value, or the string itself when it is not serialized.
     *
     * @throws MalformedSerializedValue
     */
    public static function decode(string $value): mixed
    {
        if (! self::looksSerialized($value)) {
            return $value;
        }

        if ($value === 'b:0;') {
            return false;
        }

        set_error_handler(static fn (): bool => true);

        try {
            $decoded = unserialize($value, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }

        if ($decoded === false) {
            throw MalformedSerializedValue::for($value);
        }

        return $decoded;
    }
}
