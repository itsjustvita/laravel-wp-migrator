<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Redirects;

/**
 * The form old paths are stored and looked up in: decoded, lowercase, with
 * a leading and without a trailing slash. Query URLs keep their query:
 * "/?p=12".
 */
final class RedirectPath
{
    public static function normalise(string $path): string
    {
        $query = '';
        $mark = strpos($path, '?');

        if ($mark !== false) {
            $query = substr($path, $mark);
            $path = substr($path, 0, $mark);
        }

        return '/'.trim(mb_strtolower(rawurldecode($path)), '/').mb_strtolower($query);
    }
}
