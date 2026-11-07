<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use ItsJustVita\WpMigrator\Detection\Detector;
use ItsJustVita\WpMigrator\Detection\Finding;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * Detects a feature by the presence of a table it creates.
 */
abstract class TableDetector implements Detector
{
    abstract protected function key(): string;

    abstract protected function label(): string;

    abstract protected function table(): string;

    public function detect(WpDatabase $db): Finding
    {
        if (! $db->hasTable($this->table())) {
            return Finding::none($this->key(), $this->label());
        }

        return Finding::present($this->key(), $this->label(), 'Table '.$db->tableName($this->table()).' exists');
    }
}
