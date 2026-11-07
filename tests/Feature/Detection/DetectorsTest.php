<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Detection\Detectors\BeaverBuilderDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\DiviDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\ElementorDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\MultisiteDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\OxygenDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\PolylangDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\WooCommerceDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\WpBakeryDetector;
use ItsJustVita\WpMigrator\Detection\Detectors\WpmlDetector;
use ItsJustVita\WpMigrator\Detection\Finding;
use ItsJustVita\WpMigrator\Detection\UnsupportedContentDetector;
use ItsJustVita\WpMigrator\Source\WpDatabase;

it('reports nothing on a plain site', function (string $detector) {
    $this->source()->post(['post_content' => '<p>Just text</p>']);

    $finding = (new $detector)->detect(app(WpDatabase::class));

    expect($finding)->toBeInstanceOf(Finding::class)
        ->and($finding->detected)->toBeFalse()
        ->and($finding->count)->toBe(0);
})->with([
    ElementorDetector::class,
    DiviDetector::class,
    WpBakeryDetector::class,
    BeaverBuilderDetector::class,
    OxygenDetector::class,
    WooCommerceDetector::class,
    WpmlDetector::class,
    PolylangDetector::class,
    MultisiteDetector::class,
]);

it('detects Elementor pages by their builder data, ignoring revisions', function () {
    $site = $this->source();
    $page = $site->page(['post_title' => 'Landing']);
    $site->postMeta($page, '_elementor_data', '[{"id":"abc","elType":"section"}]');
    $revision = $site->post(['post_type' => 'revision', 'post_parent' => $page]);
    $site->postMeta($revision, '_elementor_data', '[{"id":"abc","elType":"section"}]');
    $emptied = $site->page(['post_title' => 'Switched back']);
    $site->postMeta($emptied, '_elementor_data', '[]');

    $finding = (new ElementorDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('elementor')
        ->and($finding->detected)->toBeTrue()
        ->and($finding->count)->toBe(1)
        ->and($finding->sampleIds)->toBe([$page]);
});

it('detects Divi content by its shortcodes', function () {
    $site = $this->source();
    $a = $site->page(['post_content' => '[et_pb_section fb_built="1"][/et_pb_section]']);
    $b = $site->page(['post_content' => '[et_pb_row][/et_pb_row]']);
    $site->page(['post_content' => '<p>plain</p>']);

    $finding = (new DiviDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('divi')
        ->and($finding->count)->toBe(2)
        ->and($finding->sampleIds)->toBe([$a, $b]);
});

it('detects WPBakery content by its shortcodes', function () {
    $site = $this->source();
    $page = $site->page(['post_content' => '[vc_row][vc_column][/vc_column][/vc_row]']);

    $finding = (new WpBakeryDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('wpbakery')
        ->and($finding->count)->toBe(1)
        ->and($finding->sampleIds)->toBe([$page]);
});

it('detects Beaver Builder pages by their enabled flag', function () {
    $site = $this->source();
    $page = $site->page();
    $site->postMeta($page, '_fl_builder_enabled', '1');
    $off = $site->page();
    $site->postMeta($off, '_fl_builder_enabled', '0');

    $finding = (new BeaverBuilderDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('beaver-builder')
        ->and($finding->count)->toBe(1)
        ->and($finding->sampleIds)->toBe([$page]);
});

it('detects Oxygen pages by their builder shortcodes meta', function () {
    $site = $this->source();
    $page = $site->page();
    $site->postMeta($page, 'ct_builder_shortcodes', '[ct_section][/ct_section]');

    $finding = (new OxygenDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('oxygen')
        ->and($finding->count)->toBe(1);
});

it('detects WooCommerce by products and order tables', function () {
    $site = $this->source();
    $site->post(['post_type' => 'product']);
    $site->post(['post_type' => 'product_variation']);
    $site->table('posts')->getConnection()->getSchemaBuilder()->create('wp_wc_orders', function ($table): void {
        $table->bigIncrements('id');
    });

    $finding = (new WooCommerceDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('woocommerce')
        ->and($finding->detected)->toBeTrue()
        ->and($finding->count)->toBe(2)
        ->and($finding->note)->toContain('wp_wc_orders');
});

it('detects WPML by its translations table', function () {
    $this->source()->table('posts')->getConnection()->getSchemaBuilder()->create('wp_icl_translations', function ($table): void {
        $table->bigIncrements('translation_id');
    });

    $finding = (new WpmlDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('wpml')
        ->and($finding->detected)->toBeTrue()
        ->and($finding->note)->toContain('wp_icl_translations');
});

it('detects Polylang by its language taxonomy', function () {
    $site = $this->source();
    $site->term('language', 'Deutsch', ['slug' => 'de']);
    $site->term('language', 'English', ['slug' => 'en']);

    $finding = (new PolylangDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('polylang')
        ->and($finding->detected)->toBeTrue()
        ->and($finding->note)->toContain('2 languages');
});

it('detects multisite by the blogs table', function () {
    $this->source()->table('posts')->getConnection()->getSchemaBuilder()->create('wp_blogs', function ($table): void {
        $table->bigIncrements('blog_id');
    });

    $finding = (new MultisiteDetector)->detect(app(WpDatabase::class));

    expect($finding->key)->toBe('multisite')
        ->and($finding->detected)->toBeTrue();
});

it('runs all default detectors and returns one finding each', function () {
    $site = $this->source();
    $page = $site->page();
    $site->postMeta($page, '_elementor_data', '[{"id":"x"}]');

    $findings = app(UnsupportedContentDetector::class)->findings();

    expect($findings)->toHaveCount(9)
        ->and(array_map(fn (Finding $f) => $f->key, $findings))->toContain('elementor', 'divi', 'multisite')
        ->and(array_filter($findings, fn (Finding $f) => $f->detected))->toHaveCount(1);
});
