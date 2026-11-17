<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection;

use Illuminate\Config\Repository;
use ItsJustVita\WpMigrator\Detection\Detectors\BeaverBuilderDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\DiviDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\ElementorDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\ElementorThemeBuilderDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\MultisiteDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\OxygenDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\PolylangDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\WooCommerceDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\WpBakeryDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\WpmlDetector;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * Runs every detector for content the package reports but does not migrate.
 */
class UnsupportedContentDetector
{
    /** @var list<class-string<Detector>> */
    public const array DEFAULT_DETECTORS = [
        ElementorDetector::class,
        DiviDetector::class,
        WpBakeryDetector::class,
        BeaverBuilderDetector::class,
        OxygenDetector::class,
        WooCommerceDetector::class,
        WpmlDetector::class,
        PolylangDetector::class,
        MultisiteDetector::class,
    ];

    /** @var list<Detector> */
    private readonly array $detectors;

    /**
     * @param  list<Detector>|null  $detectors  defaults to {@see DEFAULT_DETECTORS}
     */
    public function __construct(
        private readonly WpDatabase $db,
        ?array $detectors = null,
        ?Repository $config = null,
    ) {
        $convertsElementor = $config?->boolean('wp-migrator.content.elementor', true) ?? true;

        $this->detectors = $detectors ?? array_map(
            // Pages built with Elementor are converted; only the theme builder is not.
            fn (string $class): Detector => $class === ElementorDetector::class && $convertsElementor ? new ElementorThemeBuilderDetector : new $class,
            self::DEFAULT_DETECTORS,
        );
    }

    /**
     * @return list<Finding>
     */
    public function findings(): array
    {
        return array_map(
            fn (Detector $detector): Finding => $detector->detect($this->db),
            $this->detectors,
        );
    }
}
