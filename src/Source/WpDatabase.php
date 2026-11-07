<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source;

use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use PDO;
use Throwable;

/**
 * Read access to the WP database through the configured Laravel connection.
 *
 * Every table name goes through {@see tableName()} so the WP table prefix is
 * applied in one place. The class only exposes query builders; nothing in the
 * package writes to the source connection.
 */
class WpDatabase
{
    private ?string $timezone = null;

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly Repository $config,
    ) {}

    public function connectionName(): string
    {
        return $this->config->string('wp-migrator.source.connection', 'wordpress');
    }

    public function connection(): Connection
    {
        return $this->databases->connection($this->connectionName());
    }

    public function prefix(): string
    {
        return $this->config->string('wp-migrator.source.table_prefix', 'wp_');
    }

    public function tableName(string $table): string
    {
        return $this->prefix().$table;
    }

    public function table(string $table): Builder
    {
        return $this->connection()->table($this->tableName($table));
    }

    /**
     * Posts that carry content: everything except revisions and auto-drafts.
     */
    public function posts(): Builder
    {
        return $this->table('posts')
            ->where('post_type', '!=', 'revision')
            ->where('post_status', '!=', 'auto-draft');
    }

    public function hasTable(string $table): bool
    {
        return $this->connection()->getSchemaBuilder()->hasTable($this->tableName($table));
    }

    /**
     * The site's timezone as WP reports it: a named zone, or an offset such
     * as "+01:00" when only gmt_offset is set.
     */
    public function timezone(): string
    {
        if ($this->timezone !== null) {
            return $this->timezone;
        }

        $name = $this->option('timezone_string');

        if ($name !== null && $name !== '') {
            return $this->timezone = $name;
        }

        $offset = $this->option('gmt_offset');

        if ($offset !== null && is_numeric($offset)) {
            $hours = (float) $offset;
            $whole = (int) abs($hours);
            $minutes = (int) round((abs($hours) - $whole) * 60);

            return $this->timezone = sprintf('%s%02d:%02d', $hours < 0 ? '-' : '+', $whole, $minutes);
        }

        return $this->timezone = 'UTC';
    }

    public function option(string $name): ?string
    {
        $value = $this->table('options')->where('option_name', $name)->value('option_value');

        return $value === null ? null : (string) $value;
    }

    /**
     * Like {@see option()}, for callers that also have to work without a
     * reachable source database.
     */
    public function optionOrNull(string $name): ?string
    {
        try {
            return $this->option($name);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Opens the connection. Throws whatever the driver throws when it cannot.
     */
    public function ping(): void
    {
        $this->connection()->getPdo();
    }

    public function serverVersion(): string
    {
        $version = $this->connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        return is_scalar($version) ? (string) $version : 'unknown';
    }
}
