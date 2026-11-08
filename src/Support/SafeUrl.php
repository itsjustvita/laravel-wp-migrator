<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Support;

/**
 * Tells links that are safe to put into an href apart from ones that run
 * script, such as javascript: or data: URLs. Browsers ignore whitespace and
 * control characters inside a scheme, so those are removed before checking.
 */
final class SafeUrl
{
    public const array DEFAULT_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * @param  list<string>  $schemes  allowed schemes; relative URLs are only allowed with the defaults
     */
    public static function isSafe(string $url, array $schemes = self::DEFAULT_SCHEMES): bool
    {
        $compact = (string) preg_replace('/[\x00-\x20\x7f]+/', '', $url);

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $compact, $matches) === 1) {
            return in_array(strtolower($matches[1]), $schemes, true);
        }

        return $schemes === self::DEFAULT_SCHEMES;
    }
}
