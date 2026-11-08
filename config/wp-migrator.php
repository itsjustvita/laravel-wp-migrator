<?php

declare(strict_types=1);
use ItsJustVita\WpMigrator\Content\Urls\DefaultUrlResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Source
    |--------------------------------------------------------------------------
    |
    | The WP site is read through an additional database connection that you
    | define in config/database.php. The package never writes to it. Uploads
    | are read from a local copy of wp-content/uploads; when a file is missing
    | there, it is downloaded from the old site URL instead.
    |
    */

    'source' => [
        'connection' => env('WP_MIGRATOR_CONNECTION', 'wordpress'),
        'table_prefix' => env('WP_MIGRATOR_TABLE_PREFIX', 'wp_'),
        'uploads_path' => env('WP_MIGRATOR_UPLOADS_PATH'),
        'site_url' => env('WP_MIGRATOR_SITE_URL'),

        // Public URL of wp-content/uploads on the old site. Derived from the
        // site URL when empty.
        'uploads_url' => env('WP_MIGRATOR_UPLOADS_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Target
    |--------------------------------------------------------------------------
    |
    | All tables created by the package migrations carry this prefix so they
    | can live next to the tables of an existing application.
    |
    */

    'target' => [
        'table_prefix' => env('WP_MIGRATOR_TARGET_TABLE_PREFIX', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Import
    |--------------------------------------------------------------------------
    |
    | Which posts are considered content. Attachments become media and menu
    | items become menus through their own importers; the other excluded
    | types are internal to WP or to plugins and carry no site content.
    |
    */

    'import' => [
        'chunk_size' => 200,

        'post_statuses' => ['publish', 'future', 'draft', 'pending', 'private'],

        'excluded_post_types' => [
            'attachment', 'revision', 'nav_menu_item',
            'custom_css', 'customize_changeset', 'oembed_cache', 'user_request',
            'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation',
            'wp_font_family', 'wp_font_face',
            'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page',
            'scheduled-action',
        ],

        'excluded_taxonomies' => [
            'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category',
            'post_translations', 'term_translations', 'term_language',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    |
    | Users are written into the application's user model. "attributes" maps
    | model attributes to source fields: login, email, display_name, nicename,
    | url, password_hash, registered_at or meta:<key>. Password hashes are
    | stored unchanged. Set "role_attribute" to store the first WP role,
    | translated through "roles" (unlisted roles are stored as they are).
    |
    */

    'users' => [
        'model' => null, // defaults to auth.providers.users.model

        'attributes' => [
            'name' => 'display_name',
            'email' => 'email',
            'password' => 'password_hash',
            'created_at' => 'registered_at',
        ],

        'role_attribute' => null,

        'roles' => [
            // 'administrator' => 'admin',
            // 'subscriber' => 'member',
        ],

        // Link WP users to existing application users with the same email
        // address instead of creating a second account. Linked users are
        // never changed by the import.
        'link_existing_by_email' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Passwords
    |--------------------------------------------------------------------------
    |
    | Set HASH_DRIVER=wordpress to let imported users log in with their WP
    | password. The "wordpress" driver verifies WP hashes and replaces each
    | one with a hash of "driver" at the user's next login. Plain MD5 hashes
    | from very old installations are only accepted when "allow_md5" is on.
    |
    */

    'passwords' => [
        'driver' => env('WP_MIGRATOR_PASSWORD_DRIVER', 'bcrypt'),
        'allow_md5' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Attachments are copied onto this disk below "directory", keeping the
    | year/month layout of wp-content/uploads.
    |
    */

    'media' => [
        'disk' => env('WP_MIGRATOR_MEDIA_DISK', 'public'),
        'directory' => 'wp-uploads',
        'download_missing' => true,
        'download_timeout' => 30,

        // Only files of these types are copied. SVG and HTML files can run
        // script in the browser and are left out on purpose. Files a web
        // server could execute, such as .php, are never copied.
        'allowed_extensions' => [
            'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'tif', 'tiff', 'heic', 'heif',
            'mp3', 'm4a', 'ogg', 'oga', 'wav', 'flac', 'aac', 'opus',
            'mp4', 'm4v', 'mov', 'webm', 'ogv', 'avi', 'wmv', 'mpg', 'mpeg', '3gp',
            'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'csv', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp',
            'key', 'pages', 'numbers', 'epub', 'zip', 'vtt', 'srt',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    |
    | The deepest nesting of arrays and objects in the stored block tree.
    | MariaDB refuses JSON deeper than 31, MySQL deeper than 100. Blocks
    | that would nest deeper are kept as HTML inside their parent.
    |
    */

    'content' => [
        'max_json_depth' => 31,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom fields
    |--------------------------------------------------------------------------
    |
    | Post meta is copied into posts.fields. Keys starting with "_" are
    | internal and skipped unless they match "allowed_internal_keys"; keys
    | matching "excluded_keys" are always skipped. Both accept * wildcards.
    |
    */

    'fields' => [
        // Convert ACF fields into typed values: media, posts, users and terms
        // become the IDs of what they were imported as, repeaters and
        // flexible content become lists, groups become nested objects.
        'acf' => true,

        'allowed_internal_keys' => [],

        'excluded_keys' => [
            'rank_math_*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Comments
    |--------------------------------------------------------------------------
    |
    | Comments are only imported when enabled. "statuses" maps the WP status
    | of the comments to import to the status they get; spam and trashed
    | comments are left out unless you add them. "types" lists the comment
    | types to import: "comment" and "" for older installations. Pingbacks,
    | trackbacks and plugin types such as reviews are left out.
    |
    */

    'comments' => [
        'enabled' => false,

        'statuses' => [
            '1' => 'approved',
            '0' => 'pending',
        ],

        'types' => ['comment', ''],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    |
    | The per-post settings of Yoast SEO and Rank Math go into seo_meta for
    | whatever model a post was mapped onto. Render them with
    | <x-wp-seo :model="$post" />.
    |
    */

    'seo' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Links to the old site inside content are rewritten. Upload URLs point to
    | the new media files, post links to the URL the resolver returns. The
    | default resolver fills these patterns, chosen by post type with "*" as
    | the fallback. Tokens: {slug}, {path}, {type}, {id}, {year}, {month}, {day}.
    |
    | "old_hosts" lists further hosts that count as the old site. The rewrite
    | slugs of custom post types are read from the source where possible; set
    | them in "post_type_slugs" where that fails.
    |
    */

    'urls' => [
        'resolver' => DefaultUrlResolver::class,

        'patterns' => [
            'page' => '/{path}',
            'post' => '/blog/{slug}',
            '*' => '/{type}/{slug}',
        ],

        // Where category, tag and author archives went, for their redirects.
        // Tokens: {slug}, {path} (with parent categories), {taxonomy}, {id}.
        // Set an entry to null to leave those archives without a redirect.
        'term_patterns' => [
            'category' => '/blog/category/{path}',
            'post_tag' => '/blog/tag/{slug}',
        ],

        'author_pattern' => '/blog/author/{slug}',

        'old_hosts' => [],

        'post_type_slugs' => [
            // 'event' => 'veranstaltungen',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Report
    |--------------------------------------------------------------------------
    */

    'report' => [
        'directory' => storage_path('logs'),
    ],

];
