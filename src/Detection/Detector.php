<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection;

use ItsJustVita\WpMigrator\Source\WpDatabase;

interface Detector
{
    public function detect(WpDatabase $db): Finding;
}
