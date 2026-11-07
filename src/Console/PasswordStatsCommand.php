<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ItsJustVita\WpMigrator\Auth\WpHashFormat;

class PasswordStatsCommand extends Command
{
    private const array LABELS = [
        'phpass' => 'phpass (WP before 6.8)',
        'wp-bcrypt' => 'bcrypt with WP prefix (WP 6.8 and later)',
        'md5' => 'md5 (very old WP)',
        'native' => 'Current hash',
        'empty' => 'No password',
        'unknown' => 'Unknown format',
    ];

    protected $signature = 'wp:password-stats';

    protected $description = 'Show how many users still have a password hash from WP';

    public function handle(Repository $config): int
    {
        [$class, $attribute] = $this->userModel($config);
        $model = new $class;

        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        $total = 0;

        $hashes = $class::query()->toBase()->select([$model->getKeyName(), $attribute])->lazyById(1000, $model->getKeyName());

        foreach ($hashes as $row) {
            $hash = $row->{$attribute} ?? null;
            $counts[WpHashFormat::detect(is_string($hash) ? $hash : null)->value]++;
            $total++;
        }

        $this->newLine();

        foreach (self::LABELS as $format => $label) {
            $this->components->twoColumnDetail($label, (string) $counts[$format]);
        }

        $legacy = $counts['phpass'] + $counts['wp-bcrypt'] + $counts['md5'];

        $this->newLine();

        if ($legacy === 0) {
            $this->components->info('No user has a WP hash any more.');

            return self::SUCCESS;
        }

        $this->components->warn(sprintf(
            '%d of %d users still have a WP hash. Each one is replaced at that user\'s next login.',
            $legacy,
            $total,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{0: class-string<Model>, 1: string}
     */
    private function userModel(Repository $config): array
    {
        $class = $config->get('wp-migrator.users.model') ?? $config->get('auth.providers.users.model');

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException('wp-migrator.users.model must name an Eloquent model class.');
        }

        $attributes = $config->get('wp-migrator.users.attributes', []);
        $attribute = is_array($attributes) ? array_search('password_hash', $attributes, true) : false;

        return [$class, is_string($attribute) ? $attribute : 'password'];
    }
}
