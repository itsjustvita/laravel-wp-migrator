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
| `--only` | Comma-separated importers: `users`, `media`, `terms`, `posts`, `pages`. `posts` covers every post type except pages. |
| `--type` | Comma-separated post types to import. |
| `--since` | Only posts and media modified on or after this date. |
| `--dry-run` | Rolls every change back at the end and copies no files. |
| `--force` | Maps rows again even when they are unchanged, for example after changing the configuration. |

Imports are idempotent. Unchanged rows are skipped, changed rows are updated, and an aborted run can simply be started again. Rows with references that could not be resolved are mapped again on the next run. Every run writes a report to `storage/logs/wp-migrator-<timestamp>.md`.

Menus, comments and redirects are not available yet.

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

### Users

Users go into your application's user model. Original password hashes are stored unchanged. A user whose email address already exists in your application is linked to that record, and the import never changes it. Roles and attributes are mapped in the `users` section of the configuration.

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
