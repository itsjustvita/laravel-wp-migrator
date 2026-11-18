<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Support;

/**
 * Tells files a web server could run or be configured by apart from media.
 * Every extension of a name counts, not only the last one, because a backdoor
 * is often hidden behind a harmless one, as in "shell.php.jpg".
 */
final class ExecutableFile
{
    /** Extensions a web server may hand to an interpreter. */
    private const string CODE = '/^(?:php\d*|phtml|pht|phps|phar|inc|cgi|fcgi|pl|py|rb|sh|bash|asp|aspx|ashx|asmx|cer|jsp|jspx|shtml?|exe|dll|bat|cmd|com)$/';

    /** Files that change how a web server treats a directory. */
    private const string SERVER_CONFIG = '/^(?:htaccess|htpasswd|ini)$/';

    /**
     * Whether the file holds code a web server could run.
     */
    public static function isCode(string $name): bool
    {
        return self::matches($name, self::CODE);
    }

    /**
     * Whether the file holds code or server configuration.
     */
    public static function isCodeOrConfig(string $name): bool
    {
        return self::isCode($name) || self::matches($name, self::SERVER_CONFIG);
    }

    private static function matches(string $name, string $pattern): bool
    {
        foreach (array_slice(explode('.', strtolower(basename($name))), 1) as $extension) {
            if (preg_match($pattern, $extension) === 1) {
                return true;
            }
        }

        return false;
    }
}
