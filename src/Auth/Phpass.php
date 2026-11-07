<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Auth;

/**
 * Verifies portable phpass hashes, the format WP used before 6.8:
 *
 *     $P$ + rounds character + 8 salt characters + 22 hash characters
 *
 * The digest is an MD5 of salt and password, run again over itself and the
 * password 2^n times, where n is the position of the rounds character in the
 * alphabet below. The 16 digest bytes are written in that same alphabet, six
 * bits at a time, starting with the lowest bits of each group of three bytes.
 */
final class Phpass
{
    private const string ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private const int LENGTH = 34;

    private const int MIN_ROUNDS = 7;

    /**
     * WP writes 13 (8192 iterations). The format allows up to 30, a billion
     * iterations, which would let one crafted hash block a login for minutes.
     */
    private const int MAX_ROUNDS = 20;

    public static function isHash(string $hash): bool
    {
        return strlen($hash) === self::LENGTH && (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$'));
    }

    public static function check(string $password, string $hash): bool
    {
        if (! self::isHash($hash)) {
            return false;
        }

        $rounds = strpos(self::ALPHABET, $hash[3]);

        if ($rounds === false || $rounds < self::MIN_ROUNDS || $rounds > self::MAX_ROUNDS) {
            return false;
        }

        $salt = substr($hash, 4, 8);
        $digest = md5($salt.$password, true);

        for ($i = 1 << $rounds; $i > 0; $i--) {
            $digest = md5($digest.$password, true);
        }

        return hash_equals($hash, substr($hash, 0, 12).self::encode($digest));
    }

    private static function encode(string $bytes): string
    {
        $encoded = '';
        $length = strlen($bytes);

        for ($offset = 0; $offset < $length; $offset += 3) {
            $group = substr($bytes, $offset, 3);
            $value = 0;

            foreach (str_split($group) as $index => $byte) {
                $value |= ord($byte) << (8 * $index);
            }

            // Three bytes make four characters; a shorter final group one more than its bytes.
            for ($char = 0; $char <= strlen($group); $char++) {
                $encoded .= self::ALPHABET[($value >> (6 * $char)) & 0x3F];
            }
        }

        return $encoded;
    }
}
