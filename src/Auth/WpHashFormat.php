<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Auth;

/**
 * The formats a password hash imported from WP can have.
 */
enum WpHashFormat: string
{
    /** Portable phpass, used before WP 6.8. */
    case Phpass = 'phpass';

    /** bcrypt over an HMAC-SHA384 of the password, prefixed with "$wp", since WP 6.8. */
    case WpBcrypt = 'wp-bcrypt';

    /** Plain MD5 from installations older than WP 2.5. */
    case Md5 = 'md5';

    /** Any hash PHP itself recognises, such as bcrypt or Argon2. */
    case Native = 'native';

    case Empty = 'empty';

    case Unknown = 'unknown';

    public static function detect(?string $hash): self
    {
        if ($hash === null || $hash === '') {
            return self::Empty;
        }

        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return self::Phpass;
        }

        if (str_starts_with($hash, '$wp$')) {
            return self::WpBcrypt;
        }

        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return self::Md5;
        }

        return password_get_info($hash)['algo'] === null ? self::Unknown : self::Native;
    }

    /**
     * Whether the format comes from WP and should be replaced at the next login.
     */
    public function isLegacy(): bool
    {
        return in_array($this, [self::Phpass, self::WpBcrypt, self::Md5], true);
    }
}
