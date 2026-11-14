<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Inventory\PluginCatalog;

it('classifies known plugins', function (string $file, string $status, string $name, string $hint) {
    $plugin = (new PluginCatalog)->classify($file);

    expect($plugin->status)->toBe($status)
        ->and($plugin->name)->toBe($name)
        ->and($plugin->advice)->toContain($hint);
})->with([
    ['wordpress-seo/wp-seo.php', 'handled', 'Yoast SEO', 'seo_meta'],
    ['seo-by-rank-math/rank-math.php', 'handled', 'Rank Math', 'seo_meta'],
    ['advanced-custom-fields-pro/acf.php', 'handled', 'Advanced Custom Fields PRO', 'fields'],
    ['secure-custom-fields/secure-custom-fields.php', 'handled', 'Secure Custom Fields', 'fields'],
    ['redirection/redirection.php', 'replace', 'Redirection', 'redirects'],
    ['contact-form-7/wp-contact-form-7.php', 'replace', 'Contact Form 7', 'form'],
    ['relevanssi/relevanssi.php', 'replace', 'Relevanssi', 'Laravel Scout'],
    ['user-role-editor/user-role-editor.php', 'replace', 'User Role Editor', 'roles'],
    ['elementor/elementor.php', 'unsupported', 'Elementor', 'rebuild'],
    ['woocommerce/woocommerce.php', 'unsupported', 'WooCommerce', 'shop'],
    ['sitepress-multilingual-cms/sitepress.php', 'unsupported', 'WPML', 'languages'],
    ['wordfence/wordfence.php', 'obsolete', 'Wordfence Security', 'Laravel'],
    ['classic-editor/classic-editor.php', 'obsolete', 'Classic Editor', 'editor'],
]);

it('lists unknown plugins by their folder', function (string $file, string $name) {
    $plugin = (new PluginCatalog)->classify($file);

    expect($plugin->status)->toBe('unknown')
        ->and($plugin->name)->toBe($name)
        ->and($plugin->file)->toBe($file);
})->with([
    ['acme-custom-stuff/acme.php', 'acme-custom-stuff'],
    ['hello.php', 'hello'],
]);
