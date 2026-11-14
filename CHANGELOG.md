# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/). Until 1.0, minor versions may change the public API.

## [Unreleased]

### Added

- `wp:inventory` scans the theme without running it and reads the source database, then writes a migration plan to `docs/wp-migration-plan.md`: a checklist of the routes, views and components to build, the shortcodes and blocks that need a handler or view, the plugins that need a replacement, the assets to move to Vite and the content that is not migrated.
- The README walks through a migration step by step, from the first import to switching the DNS.

## [0.1.0] - 2026-11-08

The first public version.

### Added

- `wp:check` validates the source connection and paths, counts the content and lists content the package does not migrate, such as page builders, WooCommerce and multilingual plugins.
- `wp:import` imports users, media, terms, posts, pages, custom post types, menus, comments and redirects into a clean schema. Runs are idempotent and resumable, support `--only`, `--type`, `--since`, `--dry-run` and `--force`, and write a Markdown report.
- Custom mappers per post type, taxonomy, users, media, menus and comments.
- Block content is parsed into a tree, classic content gets paragraphs, shortcodes are rendered through handlers for caption, gallery, embed, video and audio, and links to the old site point to the new URLs.
- `<x-wp-blocks>` renders the block tree with publishable views per block, and `<x-wp-seo>` renders the imported SEO tags.
- ACF fields become typed values, including repeaters, flexible content, groups and references to media, posts, users and terms.
- Yoast SEO and Rank Math settings are imported with their template variables resolved.
- The `wordpress` hashing driver lets users log in with their old password and replaces the hash at the next login. `wp:password-stats` shows how many old hashes are left.
- Redirects from the old URLs of posts, pages, archives, attachment pages and query URLs, a middleware that answers 404s with them, and `wp:redirects:export` for nginx, Apache and CSV.

### Not yet included

- The theme inventory and the migration plan generator.
- Content of page builders, WooCommerce, multilingual plugins and multisite installations is detected and reported, but not migrated.

[Unreleased]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/itsjustvita/laravel-wp-migrator/releases/tag/v0.1.0
