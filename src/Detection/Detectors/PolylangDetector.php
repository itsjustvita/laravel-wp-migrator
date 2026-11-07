<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use ItsJustVita\WpMigrator\Detection\Detector;
use ItsJustVita\WpMigrator\Detection\Finding;
use ItsJustVita\WpMigrator\Source\WpDatabase;

final class PolylangDetector implements Detector
{
    public function detect(WpDatabase $db): Finding
    {
        $languages = $db->table('term_taxonomy')->where('taxonomy', 'language')->count();

        if ($languages === 0) {
            return Finding::none('polylang', 'Polylang (multilingual)');
        }

        return Finding::present('polylang', 'Polylang (multilingual)', $languages.' languages in the language taxonomy');
    }
}
