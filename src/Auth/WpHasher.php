<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Auth;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

/**
 * The "wordpress" hashing driver. It verifies the hash formats of WP and
 * hands everything else, including new hashes, to a regular Laravel driver.
 *
 * WP hashes report that they need a rehash, so Laravel replaces them with a
 * regular hash at the next successful login (hashing.rehash_on_login).
 *
 * Like the WP login, it ignores spaces around the password when checking a
 * WP hash: WP trims a password both when it hashes and when a user logs in.
 */
final class WpHasher implements Hasher
{
    private const string WP_BCRYPT_PREFIX = '$wp';

    private const string WP_BCRYPT_KEY = 'wp-sha384';

    public function __construct(
        private readonly Hasher $inner,
        private readonly Repository $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function info($hashedValue): array
    {
        $format = WpHashFormat::detect($hashedValue);

        if ($format->isLegacy()) {
            return ['algo' => null, 'algoName' => $format->value, 'options' => []];
        }

        return $this->inner->info($hashedValue);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function make(#[SensitiveParameter] $value, array $options = []): string
    {
        return $this->inner->make($value, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function check(#[SensitiveParameter] $value, $hashedValue, array $options = []): bool
    {
        $password = (string) $value;

        return match (WpHashFormat::detect($hashedValue)) {
            WpHashFormat::Phpass => Phpass::check(trim($password), $hashedValue),
            WpHashFormat::WpBcrypt => $this->checkWpBcrypt(trim($password), $hashedValue),
            WpHashFormat::Md5 => $this->config->boolean('wp-migrator.passwords.allow_md5')
                && hash_equals(strtolower($hashedValue), md5(trim($password))),
            WpHashFormat::Native => $this->inner->check($value, $hashedValue, $options),
            WpHashFormat::Empty, WpHashFormat::Unknown => false,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        $format = WpHashFormat::detect($hashedValue);

        return $format->isLegacy() || $this->inner->needsRehash($hashedValue, $options);
    }

    /**
     * Called by the "hashed" cast for values that already look hashed.
     */
    public function verifyConfiguration(mixed $value): bool
    {
        return ! method_exists($this->inner, 'verifyConfiguration') || (bool) $this->inner->verifyConfiguration($value);
    }

    /**
     * WP 6.8 hashes an HMAC-SHA384 of the password, base64 encoded, with
     * bcrypt, so passwords longer than bcrypt's 72 bytes keep their entropy.
     */
    private function checkWpBcrypt(string $password, string $hash): bool
    {
        $prehashed = base64_encode(hash_hmac('sha384', $password, self::WP_BCRYPT_KEY, true));

        return password_verify($prehashed, substr($hash, strlen(self::WP_BCRYPT_PREFIX)));
    }
}
