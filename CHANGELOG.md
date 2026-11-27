# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/). Since 1.0, breaking changes to the public API come only with a new major version; the README lists what the public API covers.

## [Unreleased]

### Fixed

- Menu items that link to a trashed or deleted post are left out, as WP hides them. They used to be imported as entries without a link.
- When the content a menu item links to is no longer published, the entries below that item move up one level instead of being deleted with it, and `wp:import --force` no longer fails for them with a foreign key error.
- A row whose mapper deleted its record and returned nothing is forgotten, so other rows no longer resolve references to a record that is gone.

## [1.4.0] - 2026-11-18

### Added

- `wp:scaffold` turns the migration plan into a working start for the frontend: routes from the URL patterns, a controller for the front page, single posts and pages, archives, taxonomy archives and search, a helper for links, views under the names the plan uses, layouts for the page templates, components for the header, the footer and the menus, and views for blocks without one, listing the settings Elementor widgets hold. Existing files are kept unless `--force` is passed. Names from the old site that end up in code, such as post types, taxonomies, menu locations and widget settings, must match the key formats of WP or are left out with a note, and all other text from the old site is escaped, so a hacked site cannot inject code into the generated files. Widget fields are printed escaped.
- `wp:inventory` and `wp:scaffold` read which page WP shows on the front page.

### Changed

- The rules for the routes, views and layouts of the new frontend moved into one class that the migration plan and the scaffold share; the plan reads the same.

## [1.3.1] - 2026-11-18

### Changed

- `wp:check` lists the post types and taxonomies that become posts and terms first. Those the import handles otherwise or leaves out, such as attachments, menu items, reusable blocks and the field definitions of ACF, follow at the end, each with what happens to it.

## [1.3.0] - 2026-11-18

### Added

- `wp:check`, the import report and the migration plan list signs of a hacked site: scripts, event handlers, links that run script, frames from unknown sites, hidden links and obfuscated code in the content that is imported, including Elementor data; code in options; and program files in the uploads. Nothing is changed; each finding names the posts, options or files to check.

### Changed

- The media import and the check of the uploads share one list of program file extensions.

## [1.2.0] - 2026-11-18

### Added

- Pages built with Elementor are converted from their Elementor data into blocks. Containers, sections and columns keep the layout, and the common widgets become core blocks: heading, text, image, button, video, divider, spacer, icon list, HTML, shortcode, image gallery, icon box, image box, testimonial, accordion, toggle and tabs. Global widgets and templates are replaced by their content, up to 200 times per page and 10 levels deep. Other widgets become `elementor/<widget>` blocks with all their settings, ready for a view. Links that could run script are left out, and plain text fields are escaped. Set `content.elementor` to `false` for the previous behaviour.
- A view for `core/details`.
- `wp:check`, the report and the migration plan list templates of the Elementor theme builder, such as headers, footers and popups, as content that is not migrated. The migration plan says how many pages are converted from Elementor and lists their widgets without a view.

### Changed

- Elementor pages are no longer reported as content that is not migrated while `content.elementor` is on. Elementor counts as covered by the import; Elementor Pro and PRO Elements as needing a replacement for their theme builder, popups and forms.

## [1.1.0] - 2026-11-14

Changes from the first trial migration of a real site.

### Added

- `users.excluded` leaves users out of the import, by ID, login or email address; a string of digits counts as all three. A hacked site can hold an attacker's account, which would otherwise be imported with a working password. References to excluded users become empty, the report warns about an excluded user that an earlier run imported, and `wp:check` and the report name entries that match no user.
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

[1.4.0]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/itsjustvita/laravel-wp-migrator/compare/v0.1.0...v1.0.0
[0.1.0]: https://github.com/itsjustvita/laravel-wp-migrator/releases/tag/v0.1.0
