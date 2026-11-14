# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/). Since 1.0, breaking changes to the public API come only with a new major version; the README lists what the public API covers.

## [Unreleased]

Changes from the first trial migration of a real site.

### Added

- `users.excluded` leaves users out of the import, by ID, login or email address. A hacked site can hold an attacker's account, which would otherwise be imported with a working password. References to excluded users become empty, and the report warns about an excluded user that an earlier run imported.
- `wp:check` lists the administrators with their email address and registration date, and the migration plan asks to review them before the import.
- The migration plan lists post types without pages of their own in a section of their own instead of giving them routes: those the theme registers as not public, and those the rewrite rules of the site do not lead to. Such post types often hold form entries or other personal data.
- When no plugin is active, the migration plan lists the plugins installed next to the theme. Brevo, Brevo for WooCommerce and PRO Elements are classified.

### Changed

- `import.excluded_post_types` also leaves out the forms of Contact Form 7, the templates of Elementor, the entries of Real Cookie Banner and the orders, refunds, coupons and variations of WooCommerce. If you published the configuration, add these to your copy: `wpcf7_contact_form`, `elementor_library`, `rcb-cookie`, `rcb-banner-link`, `rcb-blocker`, `shop_order`, `shop_order_refund`, `shop_order_placehold`, `shop_coupon`, `product_variation`.
- The migration plan lists four or more unknown blocks of one namespace as one item, leaves out the blog when the site has no posts, and leaves out archives of plugin taxonomies the site had no URLs for.

## [1.0.0] - 2026-11-14

The theme inventory completes the package, and the public API is stable from here on.

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

[Unreleased]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v0.1.0...v1.0.0
[0.1.0]: https://github.com/itsjustvita/laravel-wp-migrator/releases/tag/v0.1.0
