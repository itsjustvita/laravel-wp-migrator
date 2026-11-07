# laravel-wp-migrator

Move a WP site's content into a clean Laravel application without losing data, URLs or rankings.

The package migrates posts, pages, custom post types, media, taxonomies, menus, users and comments into its own schema, parses block and classic content into a renderable structure, imports SEO meta and custom fields, keeps old URLs alive through redirects, and lets existing users keep their passwords. It does not convert theme or plugin code. Instead, it produces an inventory of the theme and a migration plan for rebuilding the frontend in Blade.

> **Status:** early development. The public API is not stable yet.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- Read access to the source database (WP 5.0 or newer, single site)

## Installation

```bash
composer require itsjustvita/laravel-wp-migrator
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=wp-migrator-config
```

Then add a connection for the source database to `config/database.php` and point `WP_MIGRATOR_CONNECTION` at it. The package only ever reads from that connection.

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=wp-migrator-migrations
php artisan migrate
```

## Usage

Check the connection and paths, and see what the site contains:

```bash
php artisan wp:check
```

Import the content. The importers run in dependency order: users, media, terms, posts.

```bash
php artisan wp:import
php artisan wp:import --dry-run
php artisan wp:import --only=users,media
php artisan wp:import --type=event --since=2026-10-01
```

| Option | Effect |
| --- | --- |
| `--only` | Comma-separated importers: `users`, `media`, `terms`, `posts`, `pages`, `redirects`. `posts` covers every post type except pages. |
| `--type` | Comma-separated post types to import. |
| `--since` | Only posts and media modified on or after this date. |
| `--dry-run` | Rolls every change back at the end and copies no files. |
| `--force` | Maps rows again even when they are unchanged, for example after changing the configuration. |

Imports are idempotent. Unchanged rows are skipped, changed rows are updated, and an aborted run can simply be started again. A post that refers to a post imported later in the same run is mapped once more at the end of the run. Whatever still cannot be resolved is mapped again on the next run. Every run writes a report to `storage/logs/wp-migrator-<timestamp>.md`.

Menus and comments are not available yet.

Media files are only copied when their extension is listed in `media.allowed_extensions`. Files a web server could execute, such as `.php`, are never copied, also not behind a second extension like `shell.php.jpg`: hacked sites often hide a backdoor in their uploads. SVG and HTML files can run script in the browser and are left out by default.

### Content

`content_raw` keeps the original HTML. `blocks` holds the content as a tree of `{name, attrs, innerHTML, innerBlocks}`, ready to render:

```blade
<x-wp-blocks :blocks="$post->blocks" />
```

Block content is parsed from its comment markers. Malformed markers never fail an import, they stay plain HTML. Content without markers is classic content: it gets paragraphs and line breaks and ends up as a single `core/freeform` block. References to reusable blocks are replaced by their content.

Links to the old site are rewritten. Upload URLs, including resized variants, point to the new media files. Post links point to the URL the resolver returns, by default from the patterns in the `urls` section of the configuration. For routes those patterns cannot express, implement `ItsJustVita\WpMigrator\Content\Urls\UrlResolver` and set it as `urls.resolver`. New URLs are built from the source site, so a link works even when its target is imported later in the same run. Whatever cannot be resolved stays in place and is listed in the report.

The stored HTML comes from the old site and is rendered as it is, the same way WP treats post content. Only import sites whose authors you trust.

### Rendering

Every common core block has its own view. They output the stored HTML, including the `wp-block-*` classes. The package ships no CSS, so style those classes or publish the views and write your own markup:

```bash
php artisan vendor:publish --tag=wp-migrator-views
```

Views receive `$block`, `$attrs` and `$content`, which is the stored HTML with inner blocks already rendered. `$block->unwrapped()` returns the HTML inside the outermost element:

```blade
{{-- resources/views/vendor/wp-migrator/blocks/heading.blade.php --}}
<h{{ $attrs['level'] ?? 2 }} class="text-2xl">{!! $block->unwrapped() !!}</h{{ $attrs['level'] ?? 2 }}>
```

A block from another namespace looks for a view of its name, so `acme/hero` uses `blocks/acme/hero.blade.php`. You can also point a block at any view of your application:

```php
WpMigrator::renderBlockUsing('acme/hero', 'components.hero');
```

Blocks without a view render their stored HTML and are listed in the report. Dynamic blocks such as `core/latest-posts` store no HTML, so they render nothing until you give them a view.

### Shortcodes

Handlers ship for `caption`, `gallery`, `embed`, `video` and `audio`. Unknown shortcodes stay in the content and are listed in the report with their number of occurrences and example posts. Add your own handler for them:

```php
use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeContext;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;

class NoticeHandler implements ShortcodeHandler
{
    public function render(Shortcode $shortcode, ShortcodeContext $context): string
    {
        return '<aside class="notice">'.$context->process($shortcode->content ?? '').'</aside>';
    }
}

WpMigrator::mapShortcode('notice', NoticeHandler::class);
```

Embeds make no network requests. YouTube and Vimeo get a player built from the URL, using the privacy-friendly YouTube domain, and everything else becomes a link.

After changing a handler, the URL patterns or other configuration, run `wp:import --force` to map unchanged rows again.

### Custom fields

Post meta ends up in `posts.fields`. Keys starting with an underscore are internal and skipped unless you allow them in `fields.allowed_internal_keys`.

ACF fields are converted to typed values, using the field definitions stored in the source database:

| ACF type | Stored as |
| --- | --- |
| true/false, number, range | boolean, integer or float |
| date picker, date time picker | `2026-01-15`, or ISO 8601 with the site's timezone |
| image, file, gallery | IDs of the imported media |
| post object, relationship, page link | IDs of what those posts were imported as |
| user, taxonomy | IDs of the imported users and terms |
| repeater | list of rows |
| flexible content | list of rows, each with a `layout` key |
| group | nested object |
| wysiwyg | HTML with paragraphs, rendered shortcodes and rewritten links |
| url, link | with links to the old site rewritten |

Fields without a definition in the database, such as field groups registered only in PHP or local JSON, are copied as they are.

### SEO

The per-post settings of Yoast SEO and Rank Math go into `seo_meta`: title, description, canonical URL, robots and the Open Graph fields. Template variables such as `%%title%%`, `%%sep%%`, `%%sitename%%` and `%%excerpt%%`, or their Rank Math equivalents, are resolved during the import. Variables that cannot be resolved are left out and listed in the report. Posts without their own SEO title get none; the site-wide default templates of the plugins are not applied.

Render the tags in your layout:

```blade
<head>
    <x-wp-seo :model="$post" />
</head>
```

The title falls back to the model's `title`. SEO settings attach to whatever model a post was mapped onto. Add the `ItsJustVita\WpMigrator\Models\Concerns\HasSeoMeta` trait to your own models to eager load them with `->with('seo')`.

### Users and passwords

Users go into your application's user model. Original password hashes are stored unchanged. A user whose email address already exists in your application is linked to that record, and the import never changes it. Roles and attributes are mapped in the `users` section of the configuration.

To let imported users log in with their old password, switch Laravel to the `wordpress` hashing driver:

```dotenv
HASH_DRIVER=wordpress
```

It verifies phpass hashes from WP before 6.8 and the prefixed bcrypt hashes from WP 6.8 and later. Plain MD5 hashes from very old installations are only accepted with `passwords.allow_md5`. Like the WP login, it ignores spaces around the password. Everything else, including every new hash, is handled by the driver in `passwords.driver`, bcrypt by default.

Laravel's login replaces a WP hash with a regular one at the first successful login, as long as `hashing.rehash_on_login` is on, which is the default. This works with `Auth::attempt()`, the starter kits and Fortify. If you authenticate users yourself, for example with `Fortify::authenticateUsing()`, check the password with `Hash::check()` and rehash it when `Hash::needsRehash()` says so.

See how many users still have a WP hash:

```bash
php artisan wp:password-stats
```

### Redirects

The `redirects` importer runs last. It works out the URLs of the old site from its permalink settings: posts, pages with their hierarchy, custom post types, category, tag and author archives, attachment pages, and query URLs such as `/?p=123`. Each one gets a redirect to its new URL from the URL resolver, unless the URL stays the same. Attachment pages redirect to the file. Set the archive patterns in `urls.term_patterns` and `urls.author_pattern`, or set them to `null` to leave those archives out.

Register the middleware globally, so it also sees requests no route matches. It answers a 404 for an old path with its redirect and counts the hit:

```php
// bootstrap/app.php
use ItsJustVita\WpMigrator\Http\Middleware\RedirectMissingPages;

->withMiddleware(function (Middleware $middleware) {
    $middleware->append(RedirectMissingPages::class);
})
```

For many redirects, or to keep them out of PHP, export them for the web server:

```bash
php artisan wp:redirects:export --format=nginx --output=redirects.conf
php artisan wp:redirects:export --format=apache
php artisan wp:redirects:export --format=csv
```

### Custom mappers

Every source row passes through a mapper. Register your own per post type or taxonomy in a service provider:

```php
use ItsJustVita\WpMigrator\Facades\WpMigrator;

WpMigrator::mapPostType('event', EventMapper::class);
WpMigrator::mapTaxonomy('genre', GenreMapper::class);
```

A mapper receives a source object and an import context, and returns the model it persisted, or `null` to skip the row. A `SourcePost` also carries the transformed content in `$post->blocks`:

```php
use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\PostMapper;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;

class EventMapper implements PostMapper
{
    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        $event = $context->existing(SourceType::Post, $post->id, Event::class) ?? new Event;

        $event->fill([
            'title' => $post->title,
            'starts_at' => $post->meta->get('starts_at'),
        ])->save();

        return $event;
    }
}
```

## Development

```bash
composer install
composer test
composer analyse
composer lint
```

The tests use SQLite by default. SQLite accepts unknown column names in some queries, so also run them against MySQL or MariaDB before relying on a change to a source query:

```bash
WP_MIGRATOR_TEST_DB_DRIVER=mysql \
WP_MIGRATOR_TEST_DB_HOST=127.0.0.1 \
WP_MIGRATOR_TEST_DB_PORT=3306 \
WP_MIGRATOR_TEST_DB_DATABASE=wordpress \
WP_MIGRATOR_TEST_DB_USERNAME=root \
WP_MIGRATOR_TEST_DB_PASSWORD=root \
composer test
```

These tests drop every table in the given database and in a database called `wp_migrator_target`, so point them at a throwaway server.

## Licence

MIT. See [LICENSE.md](LICENSE.md).
