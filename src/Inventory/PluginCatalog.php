<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory;

/**
 * Advice for common plugins, by the directory they are installed in.
 */
final class PluginCatalog
{
    private const string FORMS = 'Rebuild the forms with a form request and a mailable, or with a form builder such as Filament Forms, and protect them against spam.';

    private const string BUILDER = 'Content built with it is not migrated; rebuild those pages by hand.';

    private const string LANGUAGES = 'Translations are not migrated; plan how the new site handles languages before the import.';

    private const string SECURITY = 'It protects WP itself and has no counterpart in Laravel.';

    private const string CACHE = 'Use HTTP caching, the Laravel cache and Vite for assets instead.';

    private const string SEO = 'Imported into seo_meta; render it with `<x-wp-seo>`.';

    private const string FIELDS = 'Field values are imported into posts.fields as typed values.';

    private const string TRACKING = 'Add the tracking code to the layout, behind the cookie consent if the site needs one.';

    /** @var array<string, array{0: string, 1: string, 2: string}> directory => name, status, advice */
    private const array KNOWN = [
        'wordpress-seo' => ['Yoast SEO', 'handled', self::SEO],
        'wordpress-seo-premium' => ['Yoast SEO Premium', 'handled', self::SEO],
        'seo-by-rank-math' => ['Rank Math', 'handled', self::SEO],
        'seo-by-rank-math-pro' => ['Rank Math PRO', 'handled', self::SEO],
        'advanced-custom-fields' => ['Advanced Custom Fields', 'handled', self::FIELDS],
        'advanced-custom-fields-pro' => ['Advanced Custom Fields PRO', 'handled', self::FIELDS],
        'secure-custom-fields' => ['Secure Custom Fields', 'handled', self::FIELDS],
        'custom-post-type-ui' => ['Custom Post Type UI', 'handled', 'The posts of its post types are imported; give each post type a route.'],
        'redirection' => ['Redirection', 'replace', 'Its own rules are not imported. Old permalinks are covered by the redirects importer; add any other rules to the redirects table.'],
        'contact-form-7' => ['Contact Form 7', 'replace', self::FORMS],
        'wpforms-lite' => ['WPForms Lite', 'replace', self::FORMS],
        'wpforms' => ['WPForms', 'replace', self::FORMS],
        'gravityforms' => ['Gravity Forms', 'replace', self::FORMS],
        'ninja-forms' => ['Ninja Forms', 'replace', self::FORMS],
        'formidable' => ['Formidable Forms', 'replace', self::FORMS],
        'relevanssi' => ['Relevanssi', 'replace', 'Use Laravel Scout for search.'],
        'searchwp' => ['SearchWP', 'replace', 'Use Laravel Scout for search.'],
        'user-role-editor' => ['User Role Editor', 'replace', 'Map the roles with the users.roles setting and manage permissions with a package such as spatie/laravel-permission.'],
        'members' => ['Members', 'replace', 'Map the roles with the users.roles setting and manage permissions with a package such as spatie/laravel-permission.'],
        'mailchimp-for-wp' => ['Mailchimp for WordPress', 'replace', 'Connect the newsletter service through its API, for example with spatie/laravel-newsletter.'],
        'newsletter' => ['Newsletter', 'replace', 'Subscribers are not migrated; move them to a newsletter service or your own model.'],
        'akismet' => ['Akismet', 'replace', 'Protect forms and comments against spam, for example with a honeypot package.'],
        'updraftplus' => ['UpdraftPlus', 'replace', 'Back up the application, for example with spatie/laravel-backup.'],
        'backwpup' => ['BackWPup', 'replace', 'Back up the application, for example with spatie/laravel-backup.'],
        'the-events-calendar' => ['The Events Calendar', 'replace', 'Events are imported as posts of type tribe_events; map them onto your own model with a custom mapper.'],
        'tablepress' => ['TablePress', 'replace', 'Its tables are stored outside the posts; rebuild them or import them by hand.'],
        'jetpack' => ['Jetpack', 'replace', 'Check which of its modules were in use and replace them one by one.'],
        'complianz-gdpr' => ['Complianz', 'replace', 'Add a cookie consent banner to the new frontend.'],
        'cookie-notice' => ['Cookie Notice', 'replace', 'Add a cookie consent banner to the new frontend.'],
        'borlabs-cookie' => ['Borlabs Cookie', 'replace', 'Add a cookie consent banner to the new frontend.'],
        'cookiebot' => ['Cookiebot', 'replace', 'Add a cookie consent banner to the new frontend.'],
        'google-site-kit' => ['Site Kit by Google', 'replace', self::TRACKING],
        'google-analytics-for-wordpress' => ['MonsterInsights', 'replace', self::TRACKING],
        'elementor' => ['Elementor', 'unsupported', self::BUILDER],
        'elementor-pro' => ['Elementor Pro', 'unsupported', self::BUILDER],
        'js_composer' => ['WPBakery Page Builder', 'unsupported', self::BUILDER],
        'divi-builder' => ['Divi Builder', 'unsupported', self::BUILDER],
        'beaver-builder-lite-version' => ['Beaver Builder', 'unsupported', self::BUILDER],
        'bb-plugin' => ['Beaver Builder', 'unsupported', self::BUILDER],
        'oxygen' => ['Oxygen', 'unsupported', self::BUILDER],
        'brizy' => ['Brizy', 'unsupported', self::BUILDER],
        'revslider' => ['Slider Revolution', 'unsupported', 'Its sliders are stored outside the posts; rebuild them.'],
        'smart-slider-3' => ['Smart Slider 3', 'unsupported', 'Its sliders are stored outside the posts; rebuild them.'],
        'woocommerce' => ['WooCommerce', 'unsupported', 'Products and orders are not migrated; plan the shop of the new site separately.'],
        'sitepress-multilingual-cms' => ['WPML', 'unsupported', self::LANGUAGES],
        'polylang' => ['Polylang', 'unsupported', self::LANGUAGES],
        'polylang-pro' => ['Polylang Pro', 'unsupported', self::LANGUAGES],
        'translatepress-multilingual' => ['TranslatePress', 'unsupported', self::LANGUAGES],
        'wordfence' => ['Wordfence Security', 'obsolete', self::SECURITY],
        'sucuri-scanner' => ['Sucuri Security', 'obsolete', self::SECURITY],
        'all-in-one-wp-security-and-firewall' => ['All-In-One Security', 'obsolete', self::SECURITY],
        'really-simple-ssl' => ['Really Simple SSL', 'obsolete', 'Enforce HTTPS in the web server or with Laravel middleware.'],
        'classic-editor' => ['Classic Editor', 'obsolete', 'It only changes the editor; nothing to migrate.'],
        'gutenberg' => ['Gutenberg', 'obsolete', 'It only changes the editor; nothing to migrate.'],
        'duplicate-post' => ['Yoast Duplicate Post', 'obsolete', 'An editing aid; nothing to migrate.'],
        'regenerate-thumbnails' => ['Regenerate Thumbnails', 'obsolete', 'Image sizes are copied by the media import.'],
        'wp-mail-smtp' => ['WP Mail SMTP', 'obsolete', 'Configure mail in config/mail.php.'],
        'post-smtp' => ['Post SMTP', 'obsolete', 'Configure mail in config/mail.php.'],
        'wp-super-cache' => ['WP Super Cache', 'obsolete', self::CACHE],
        'w3-total-cache' => ['W3 Total Cache', 'obsolete', self::CACHE],
        'litespeed-cache' => ['LiteSpeed Cache', 'obsolete', self::CACHE],
        'wp-rocket' => ['WP Rocket', 'obsolete', self::CACHE],
        'wp-fastest-cache' => ['WP Fastest Cache', 'obsolete', self::CACHE],
        'autoptimize' => ['Autoptimize', 'obsolete', self::CACHE],
        'wp-migrate-db' => ['WP Migrate', 'obsolete', 'A WP migration tool; nothing to migrate.'],
        'all-in-one-wp-migration' => ['All-in-One WP Migration', 'obsolete', 'A WP migration tool; nothing to migrate.'],
    ];

    /**
     * @param  string  $file  as stored in the active_plugins option: "directory/file.php"
     */
    public function classify(string $file): PluginClassification
    {
        $directory = str_contains($file, '/') ? explode('/', $file)[0] : basename($file, '.php');
        $known = self::KNOWN[$directory] ?? null;

        if ($known === null) {
            return new PluginClassification($file, $directory, 'unknown', 'Not known to the package; find out what it does on the site and whether the new site needs it.');
        }

        return new PluginClassification($file, $known[0], $known[1], $known[2]);
    }
}
