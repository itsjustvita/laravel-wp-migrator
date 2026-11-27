<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Scaffold;

use ItsJustVita\WpMigrator\Inventory\Frontend;
use ItsJustVita\WpMigrator\Inventory\FrontendItem;
use ItsJustVita\WpMigrator\Inventory\Theme\Template;

/**
 * The files of a working frontend for the imported content: routes from the
 * URL patterns, a controller, views and layouts named as in the migration
 * plan, components for the menus, and views for blocks without one. The
 * code belongs to the application from then on; nothing here is used at
 * runtime.
 *
 * Names and texts from the old site end up in PHP and Blade files, and a
 * hacked site controls them. So names that are used as code must match the
 * key formats of WP, and everything else is escaped where it is written:
 * PHP strings with var_export, Blade text with entities for {, } and @,
 * and comments so they cannot be closed early.
 */
final class Scaffold
{
    /** Route parameters and the values they accept. */
    private const array WHERE = [
        'path' => '.+',
        'id' => '[0-9]+',
        'year' => '[0-9]{4}',
        'month' => '[0-9]{2}',
        'day' => '[0-9]{2}',
    ];

    /** Post types and taxonomies, as WP stores their keys. */
    private const string KEY = '/^[a-z0-9_-]{1,32}$/';

    private const string LOCATION = '/^[A-Za-z0-9_-]{1,64}$/';

    private const string BLOCK = '/^[a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?$/';

    private const string ATTRIBUTE = '/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/';

    private const string SLUG = '/^[\p{L}\p{N}_-]{1,200}$/u';

    /** More authors than this are not listed in the controller. */
    private const int MAX_AUTHORS = 500;

    /** @var list<string> */
    private array $notes = [];

    /**
     * @param  array<string, array<string, string>>  $blockAttributes  for each block, its attributes and what they hold: text, value, list or object
     */
    public function __construct(
        private readonly Frontend $frontend,
        private readonly array $blockAttributes = [],
    ) {}

    /**
     * The files, by their path in the application.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        $this->notes = [];
        $singles = $this->singles();
        $archives = $this->archives($singles);
        $terms = [];

        foreach ($this->frontend->termArchives() as $term) {
            if (! $this->usable((string) $term->subject, self::KEY, 'taxonomy')) {
                continue;
            }

            if ($term->uri !== null) {
                $terms[] = $term;
            } else {
                $this->notes[] = sprintf('The taxonomy "%s" has no URL pattern, so its archive got no route; add one to urls.term_patterns and scaffold again.', $term->subject);
            }
        }

        $search = $this->frontend->search();
        [$author, $authors] = $this->authorArchive();

        $files = [
            'routes/wp.php' => $this->routesFile($singles, $archives, $terms, $search, $author),
            'app/Http/Controllers/WpController.php' => $this->controller($singles, $terms, $author === null ? null : $authors),
            'app/Support/WpUrl.php' => $this->urlHelper(),
            'resources/views/layouts/wp.blade.php' => $this->layout(),
            'resources/views/components/wp/header.blade.php' => $this->header(),
            'resources/views/components/wp/footer.blade.php' => $this->footer(),
            'resources/views/components/wp/menu.blade.php' => $this->menu(),
            'resources/views/components/wp/menu-items.blade.php' => $this->menuItems(),
            'resources/views/components/wp/post-list.blade.php' => $this->postList(),
        ];

        $files[$this->frontend->front()->viewPath()] = $this->home($this->frontend->front());

        foreach ($singles as $single) {
            $files[$single->viewPath()] = $this->show($single);
        }

        foreach ($archives as $archive) {
            $files[$archive->viewPath()] = $this->index($archive);
        }

        foreach ($this->frontend->specific() as $specific) {
            if (isset($singles[(string) $specific->subject]) && preg_match(self::SLUG, (string) $specific->slug) === 1) {
                $files[$specific->viewPath()] = $this->show($specific);
            }
        }

        foreach ($this->layouts() as $layout) {
            $files[$layout->viewPath()] = $this->templateLayout($layout);
        }

        foreach ($terms as $term) {
            $files[$term->viewPath()] = $this->term($term);
        }

        if ($search !== null) {
            $files[$search->viewPath()] = $this->search($search);
        }

        if ($author !== null) {
            $files[$author->viewPath()] = $this->author($author);
        }

        $notFound = $this->frontend->notFound();

        if ($notFound !== null) {
            $files[$notFound->viewPath()] = $this->notFound($notFound);
        }

        foreach ($this->frontend->site->blocks as $name => $usage) {
            if ($this->usable($name, self::BLOCK, 'block')) {
                $files[$this->blockViewPath($name)] = $this->blockView($name, $usage['count']);
            }
        }

        return $files;
    }

    /**
     * What the developer should know about the files.
     *
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * The single views of the post types that get routes, by post type.
     *
     * @return array<string, FrontendItem>
     */
    private function singles(): array
    {
        $singles = [];

        foreach ($this->frontend->routedTypes() as $type) {
            if (! $this->usable($type, self::KEY, 'post type')) {
                continue;
            }

            $single = $this->frontend->single($type);

            if ($single->uri === null) {
                $this->notes[] = sprintf('The post type "%s" has no URL pattern, so it got no route; add one to urls.patterns and scaffold again.', $type);

                continue;
            }

            $singles[$type] = $single;
        }

        return $singles;
    }

    /**
     * The author archive, when it can get a route, and the authors it finds
     * by their slug: slug => user ID on the old site. The imported users
     * keep no slug, so the controller lists them; a pattern with {id} needs
     * no list.
     *
     * @return array{0: FrontendItem|null, 1: array<array-key, int>}
     */
    private function authorArchive(): array
    {
        $archive = $this->frontend->authorArchive();

        if ($archive === null || $archive->uri === null) {
            return [null, []];
        }

        if (str_contains($archive->uri, '{id}')) {
            return [$archive, []];
        }

        if (! str_contains($archive->uri, '{slug}')) {
            $this->notes[] = 'urls.author_pattern has neither {slug} nor {id}, so the author archives got no route.';

            return [null, []];
        }

        if (! $this->frontend->site->reachable) {
            $this->notes[] = 'The author archives got no route: their slugs are read from the source database, which could not be opened.';

            return [null, []];
        }

        $authors = [];

        foreach ($this->frontend->site->authors as $slug => $id) {
            if ($this->usable((string) $slug, self::SLUG, 'author')) {
                $authors[$slug] = $id;
            }
        }

        if (count($authors) > self::MAX_AUTHORS) {
            $this->notes[] = sprintf('The author archives got no route: %d authors are too many to list in the controller. Use {id} in urls.author_pattern, or find them by a column of your own.', count($authors));

            return [null, []];
        }

        return [$authors === [] ? null : $archive, $authors];
    }

    /**
     * Whether a name from the old site can be used in code. A hacked site
     * can name a post type or block so that it would inject code.
     */
    private function usable(string $name, string $pattern, string $what): bool
    {
        if (preg_match($pattern, $name) === 1) {
            return true;
        }

        $this->notes[] = sprintf('The %s "%s" was left out: its name cannot be used in code safely.', $what, $this->shown($name));

        return false;
    }

    /**
     * The layouts of the page templates, without those whose name gives no file name.
     *
     * @return list<FrontendItem>
     */
    private function layouts(): array
    {
        return array_values(array_filter($this->frontend->layouts(), fn (FrontendItem $layout): bool => $layout->view !== 'layouts.'));
    }

    /**
     * @param  array<string, FrontendItem>  $singles
     * @return array<string, FrontendItem>
     */
    private function archives(array $singles): array
    {
        $archives = [];

        foreach (array_keys($singles) as $type) {
            $archive = $this->frontend->archive($type);

            if ($archive !== null) {
                $archives[$type] = $archive;
            }
        }

        return $archives;
    }

    /**
     * @param  array<string, FrontendItem>  $singles
     * @param  array<string, FrontendItem>  $archives
     * @param  list<FrontendItem>  $terms
     */
    private function routesFile(array $singles, array $archives, array $terms, ?FrontendItem $search, ?FrontendItem $author): string
    {
        $routes = [['/', 'home', [], 'wp.home']];

        if ($search !== null) {
            $routes[] = ['/search', 'search', [], 'wp.search'];
        }

        foreach ($archives as $type => $archive) {
            $routes[] = [(string) $archive->uri, 'archive', ['type' => $type], 'wp.'.$type.'.index'];
        }

        foreach ($singles as $type => $single) {
            $routes[] = [(string) $single->uri, 'show', ['type' => $type], 'wp.'.$type.'.show'];
        }

        foreach ($terms as $term) {
            $routes[] = [(string) $term->uri, 'term', ['taxonomy' => (string) $term->subject], 'wp.term.'.$term->subject];
        }

        if ($author !== null) {
            $routes[] = [(string) $author->uri, 'author', [], 'wp.author'];
        }

        // The first route that matches answers. Patterns that start with a
        // parameter, such as /{path}, match almost anything and come last.
        // Before them come those with {path}, which takes whole paths: the
        // ones with more fixed segments first, so /blog/{path} does not
        // hide /blog/category/{path}.
        usort($routes, fn (array $a, array $b): int => $this->precedence($a[0]) <=> $this->precedence($b[0]));

        $lines = [];
        $seen = [];

        foreach ($routes as [$uri, $action, $defaults, $name]) {
            $key = (string) preg_replace('/\{[a-z]+\}/', '{}', $uri);

            if (isset($seen[$key])) {
                $this->notes[] = sprintf('The route for %s is the same as the one for %s, so it was left out; give it a URL pattern of its own.', $name, $seen[$key]);

                continue;
            }

            $seen[$key] = $name;
            $line = sprintf('Route::get(%s, [WpController::class, %s])', $this->php($uri), $this->php($action));

            foreach ($defaults as $parameter => $value) {
                $line .= sprintf('->defaults(%s, %s)', $this->php($parameter), $this->php($value));
            }

            preg_match_all('/\{([a-z]+)\}/', $uri, $parameters);

            foreach ($parameters[1] as $parameter) {
                if (isset(self::WHERE[$parameter])) {
                    $line .= sprintf('->where(%s, %s)', $this->php($parameter), $this->php(self::WHERE[$parameter]));
                }
            }

            $lines[] = $line.sprintf('->name(%s);', $this->php($name));
        }

        return <<<PHP
            <?php

            // The routes of the imported content, from the URL patterns in config/wp-migrator.php.
            // Generated by php artisan wp:scaffold. The first route that matches answers, so patterns
            // that take whole paths come late, and those that start with a parameter last.

            use App\\Http\\Controllers\\WpController;
            use Illuminate\\Support\\Facades\\Route;

            {$this->join($lines, '')}

            PHP;
    }

    /**
     * @return array{int, int, int}
     */
    private function precedence(string $uri): array
    {
        if ($this->catchAll($uri)) {
            return [1, 0, 0];
        }

        if (! str_contains($uri, '{path}')) {
            return [0, 0, 0];
        }

        $fixed = count(array_filter(explode('/', trim($uri, '/')), fn (string $segment): bool => ! str_starts_with($segment, '{')));

        return [0, 1, -$fixed];
    }

    private function catchAll(string $uri): bool
    {
        return preg_match('#^/\{#', $uri) === 1;
    }

    /**
     * @param  array<string, FrontendItem>  $singles
     * @param  list<FrontendItem>  $terms
     * @param  array<array-key, int>|null  $authors  null without an author archive
     */
    private function controller(array $singles, array $terms, ?array $authors): string
    {
        $types = $this->export(array_map(fn (FrontendItem $single): string => $this->frontend->plural((string) $single->subject), $singles));
        $taxonomies = [];

        foreach ($terms as $term) {
            $taxonomies[(string) $term->subject] = $this->frontend->termPlural((string) $term->subject);
        }

        $layouts = [];

        foreach ($this->layouts() as $layout) {
            $layouts[(string) $layout->subject] = $layout->view;
        }

        $front = var_export($this->frontend->site->frontPage, true);
        $taxonomies = $this->export($taxonomies);
        $layouts = $this->export($layouts);
        $authorList = '';
        $authorAction = '';

        if ($authors !== null) {
            $authorList = <<<PHP

                    /** The authors with an archive, by the slug in its URL: their user ID on the old site. */
                    private const array AUTHORS = {$this->export($authors)};

                PHP;

            $authorAction = <<<'PHP'

                    /**
                     * The posts of an author. The import map leads from the ID the
                     * author had on the old site to the imported user.
                     */
                    public function author(Request $request): View
                    {
                        $id = $request->route('id') ?? self::AUTHORS[(string) $request->route('slug')] ?? null;

                        $author = $id === null ? null : ImportMapEntry::query()
                            ->where('source_type', SourceType::User)
                            ->where('source_id', (int) $id)
                            ->first()?->target;

                        abort_if($author === null, 404);

                        return view('authors.show', [
                            'author' => $author,
                            'posts' => $this->published('post')->where('author_id', $author->getKey())->latest('published_at')->paginate(self::PER_PAGE),
                            'layout' => 'layouts.wp',
                        ]);
                    }

                PHP;
        }

        return <<<PHP
            <?php

            namespace App\\Http\\Controllers;

            use Illuminate\\Contracts\\View\\View;
            use Illuminate\\Database\\Eloquent\\Builder;
            use Illuminate\\Http\\Request;
            use ItsJustVita\\WpMigrator\\Import\\SourceType;
            use ItsJustVita\\WpMigrator\\Models\\ImportMapEntry;
            use ItsJustVita\\WpMigrator\\Models\\Post;
            use ItsJustVita\\WpMigrator\\Models\\Term;

            /**
             * Shows the content imported from the WP site. Generated by php artisan
             * wp:scaffold; it is yours to change, the command does not overwrite it
             * without --force.
             *
             * The routes pass the post type or taxonomy as a default, and the values
             * of the URL pattern (slug, path, id) as parameters.
             */
            class WpController
            {
                /** The page shown on the front page, by slug; null shows the latest posts. */
                private const ?string FRONT_PAGE = {$front};

                /** The view folder of each post type with pages of its own. */
                private const array TYPES = {$types};

                /** The view folder of each taxonomy with an archive. */
                private const array TAXONOMIES = {$taxonomies};

                /** The layout of each page template, by the value stored in posts.template. */
                private const array LAYOUTS = {$layouts};
            {$authorList}
                private const int PER_PAGE = 10;

                public function home(): View
                {
                    \$page = self::FRONT_PAGE === null ? null : \$this->published('page')->where('slug', self::FRONT_PAGE)->first();

                    if (\$page !== null) {
                        return view('home', ['post' => \$page, 'layout' => \$this->layout(\$page)]);
                    }

                    return view('home', [
                        'posts' => \$this->published('post')->latest('published_at')->paginate(self::PER_PAGE),
                        'layout' => 'layouts.wp',
                    ]);
                }

                public function show(Request \$request): View
                {
                    \$type = (string) \$request->route('type');
                    \$post = \$this->find(\$type, \$request);
                    \$specific = self::TYPES[\$type].'.'.rawurldecode(\$post->slug);

                    return view(view()->exists(\$specific) ? \$specific : self::TYPES[\$type].'.show', [
                        'post' => \$post,
                        'layout' => \$this->layout(\$post),
                    ]);
                }

                public function archive(Request \$request): View
                {
                    \$type = (string) \$request->route('type');

                    return view(self::TYPES[\$type].'.index', [
                        'posts' => \$this->published(\$type)->latest('published_at')->paginate(self::PER_PAGE),
                        'layout' => 'layouts.wp',
                    ]);
                }

                public function term(Request \$request): View
                {
                    \$taxonomy = (string) \$request->route('taxonomy');
                    \$slugs = explode('/', trim((string) (\$request->route('path') ?? \$request->route('slug')), '/'));
                    \$term = Term::query()->where('taxonomy', \$taxonomy)->whereIn('slug', \$this->slugs((string) end(\$slugs)))->firstOrFail();

                    return view(self::TAXONOMIES[\$taxonomy].'.show', [
                        'term' => \$term,
                        'posts' => \$term->posts()->where('status', 'publish')->latest('published_at')->paginate(self::PER_PAGE),
                        'layout' => 'layouts.wp',
                    ]);
                }

                public function search(Request \$request): View
                {
                    \$query = trim((string) \$request->query('q', ''));

                    \$posts = Post::query()
                        ->whereIn('type', array_keys(self::TYPES))
                        ->where('status', 'publish')
                        ->when(\$query === '', fn (Builder \$posts) => \$posts->whereRaw('1 = 0'))
                        ->where(fn (Builder \$posts) => \$posts->where('title', 'like', '%'.\$query.'%')->orWhere('content_raw', 'like', '%'.\$query.'%'))
                        ->latest('published_at')
                        ->paginate(self::PER_PAGE)
                        ->withQueryString();

                    return view('search', ['query' => \$query, 'posts' => \$posts, 'layout' => 'layouts.wp']);
                }
            {$authorAction}
                /**
                 * The published post a URL leads to: by its ID on the old site, by the
                 * slugs of its parents and its own, or by its slug.
                 */
                private function find(string \$type, Request \$request): Post
                {
                    \$posts = \$this->published(\$type);

                    if (\$request->route('id') !== null) {
                        \$entry = ImportMapEntry::query()
                            ->where('source_type', SourceType::Post)
                            ->where('source_id', (int) \$request->route('id'))
                            ->where('target_type', (new Post)->getMorphClass())
                            ->first();

                        return \$posts->whereKey(\$entry->target_id ?? 0)->firstOrFail();
                    }

                    if (\$request->route('path') !== null) {
                        \$slugs = array_map(rawurldecode(...), explode('/', trim((string) \$request->route('path'), '/')));

                        foreach (\$posts->whereIn('slug', \$this->slugs((string) end(\$slugs)))->get() as \$candidate) {
                            if (\$this->path(\$candidate) === \$slugs) {
                                return \$candidate;
                            }
                        }

                        abort(404);
                    }

                    return \$posts->whereIn('slug', \$this->slugs((string) \$request->route('slug')))->firstOrFail();
                }

                /**
                 * The slugs of a post and its parents, as written in a URL.
                 *
                 * @return list<string>
                 */
                private function path(Post \$post): array
                {
                    \$slugs = [rawurldecode(\$post->slug)];
                    \$parent = \$post->parent;

                    for (\$depth = 0; \$parent !== null && \$depth < 20; \$depth++) {
                        array_unshift(\$slugs, rawurldecode(\$parent->slug));
                        \$parent = \$parent->parent;
                    }

                    return \$slugs;
                }

                /**
                 * A slug as written in a URL and as WP stores it, with letters
                 * outside ASCII percent-encoded.
                 *
                 * @return list<string>
                 */
                private function slugs(string \$slug): array
                {
                    return array_values(array_unique([\$slug, strtolower(rawurlencode(\$slug))]));
                }

                /**
                 * @return Builder<Post>
                 */
                private function published(string \$type): Builder
                {
                    return Post::query()->where('type', \$type)->where('status', 'publish');
                }

                private function layout(Post \$post): string
                {
                    return self::LAYOUTS[\$post->template ?? ''] ?? 'layouts.wp';
                }
            }

            PHP;
    }

    private function urlHelper(): string
    {
        return <<<'PHP'
            <?php

            namespace App\Support;

            use Illuminate\Support\Facades\Route;
            use ItsJustVita\WpMigrator\Import\SourceType;
            use ItsJustVita\WpMigrator\Models\ImportMapEntry;
            use ItsJustVita\WpMigrator\Models\Post;
            use ItsJustVita\WpMigrator\Models\Term;

            /**
             * Links to imported posts and terms through the routes in routes/wp.php.
             * Generated by php artisan wp:scaffold.
             */
            final class WpUrl
            {
                public static function post(Post $post): string
                {
                    $name = 'wp.'.$post->type.'.show';
                    $route = Route::getRoutes()->getByName($name);

                    if ($route === null) {
                        return '#';
                    }

                    $values = [];

                    foreach ($route->parameterNames() as $parameter) {
                        $values[$parameter] = match ($parameter) {
                            'slug' => rawurldecode($post->slug),
                            'path' => implode('/', self::path($post)),
                            'id' => self::sourceId($post),
                            'year' => $post->published_at?->format('Y'),
                            'month' => $post->published_at?->format('m'),
                            'day' => $post->published_at?->format('d'),
                            default => null,
                        };
                    }

                    return route($name, $values);
                }

                public static function term(Term $term): string
                {
                    $name = 'wp.term.'.$term->taxonomy;

                    if (! Route::has($name)) {
                        return '#';
                    }

                    $slugs = [rawurldecode($term->slug)];
                    $parent = $term->parent;

                    for ($depth = 0; $parent !== null && $depth < 20; $depth++) {
                        array_unshift($slugs, rawurldecode($parent->slug));
                        $parent = $parent->parent;
                    }

                    return route($name, ['path' => implode('/', $slugs), 'slug' => end($slugs)]);
                }

                /**
                 * @return list<string>
                 */
                private static function path(Post $post): array
                {
                    $slugs = [rawurldecode($post->slug)];
                    $parent = $post->parent;

                    for ($depth = 0; $parent !== null && $depth < 20; $depth++) {
                        array_unshift($slugs, rawurldecode($parent->slug));
                        $parent = $parent->parent;
                    }

                    return $slugs;
                }

                private static function sourceId(Post $post): ?int
                {
                    return ImportMapEntry::query()
                        ->where('source_type', SourceType::Post)
                        ->where('target_type', $post->getMorphClass())
                        ->where('target_id', $post->getKey())
                        ->value('source_id');
                }
            }

            PHP;
    }

    private function layout(): string
    {
        return <<<'BLADE'
            {{-- The layout of the site. Generated by php artisan wp:scaffold. --}}
            <!DOCTYPE html>
            <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                @isset($post)
                    <x-wp-seo :model="$post" />
                @else
                    <title>@yield('title', config('app.name'))</title>
                @endisset
                @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
                    @vite(['resources/css/app.css', 'resources/js/app.js'])
                @endif
            </head>
            <body>
                <x-wp.header />

                <main class="@yield('main-class')">
                    @yield('content')
                </main>

                <x-wp.footer />
            </body>
            </html>

            BLADE;
    }

    private function header(): string
    {
        $locations = $this->locations();
        $location = $locations[0] ?? 'primary';
        $from = $this->from($this->frontend->find('partial', 'header'));

        return <<<BLADE
            {{-- The header of the site{$from}. Generated by php artisan wp:scaffold. --}}
            <header>
                <a href="{{ url('/') }}">{{ config('app.name') }}</a>

                <x-wp.menu location="{$location}" />
            </header>

            BLADE;
    }

    private function footer(): string
    {
        $from = $this->from($this->frontend->find('partial', 'footer'));
        $menus = '';

        foreach ($this->locations() as $location) {
            if (str_contains($location, 'footer')) {
                $menus .= "\n    <x-wp.menu location=\"{$location}\" />\n";
            }
        }

        return <<<BLADE
            {{-- The footer of the site{$from}. Generated by php artisan wp:scaffold. --}}
            <footer>{$menus}
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
            </footer>

            BLADE;
    }

    private function menu(): string
    {
        return <<<'BLADE'
            {{-- The menu assigned to a theme location. Generated by php artisan wp:scaffold. --}}
            @props(['location'])

            @php
                $menu = \ItsJustVita\WpMigrator\Models\Menu::query()->with('items')->get()
                    ->first(fn ($menu) => in_array($location, $menu->locations ?? [], true));
            @endphp

            @if ($menu !== null)
                <nav>
                    @include('components.wp.menu-items', ['items' => $menu->items->whereNull('parent_id')->sortBy('order'), 'all' => $menu->items])
                </nav>
            @endif

            BLADE;
    }

    private function menuItems(): string
    {
        return <<<'BLADE'
            {{-- The items of a menu and their children. Generated by php artisan wp:scaffold. --}}
            <ul>
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item->url ?? '#' }}" @if ($item->target) target="{{ $item->target }}" rel="noopener" @endif>{{ $item->title }}</a>

                        @php($children = $all->where('parent_id', $item->id)->sortBy('order'))

                        @if ($children->isNotEmpty())
                            @include('components.wp.menu-items', ['items' => $children, 'all' => $all])
                        @endif
                    </li>
                @endforeach
            </ul>

            BLADE;
    }

    private function postList(): string
    {
        return <<<'BLADE'
            {{-- A list of posts with links and pages. Generated by php artisan wp:scaffold. --}}
            @forelse ($posts as $item)
                <article>
                    <h2><a href="{{ \App\Support\WpUrl::post($item) }}">{{ $item->title }}</a></h2>

                    <p>{{ \Illuminate\Support\Str::limit(strip_tags($item->excerpt ?: (string) $item->content_raw), 200) }}</p>
                </article>
            @empty
                <p>Nothing found.</p>
            @endforelse

            {{ $posts->links() }}

            BLADE;
    }

    private function home(FrontendItem $home): string
    {
        $from = $this->from($home->template);

        return <<<BLADE
            {{-- The front page{$from}. Generated by php artisan wp:scaffold. It shows the
                 page set as front page in WP, or the latest posts. --}}
            @extends(\$layout ?? 'layouts.wp')

            @section('content')
                @isset(\$post)
                    <article>
                        <h1>{{ \$post->title }}</h1>

                        <x-wp-blocks :blocks="\$post->blocks" />
                    </article>
                @else
                    @include('components.wp.post-list', ['posts' => \$posts])
                @endisset
            @endsection

            BLADE;
    }

    private function show(FrontendItem $item): string
    {
        $what = $this->comment($item->kind === 'specific' ? 'The '.($item->subject === 'page' ? 'page' : 'post').' "'.$item->slug.'"' : 'A single post of the type "'.$item->subject.'"');
        $from = $this->from($item->template);
        $date = $item->subject === 'page' ? '' : <<<'BLADE'

                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->toFormattedDateString() }}</time>
                    @endif

            BLADE;

        return <<<BLADE
            {{-- {$what}{$from}. Generated by php artisan wp:scaffold. --}}
            @extends(\$layout ?? 'layouts.wp')

            @section('content')
                <article>
                    <h1>{{ \$post->title }}</h1>
            {$date}
                    @if (\$post->featuredMedia)
                        <img src="{{ \$post->featuredMedia->url() }}" alt="">
                    @endif

                    <x-wp-blocks :blocks="\$post->blocks" />
                </article>
            @endsection

            BLADE;
    }

    private function index(FrontendItem $archive): string
    {
        $from = $this->from($archive->template);
        $label = $this->text($archive->label);

        return <<<BLADE
            {{-- The archive of the post type "{$archive->subject}"{$from}. Generated by php artisan wp:scaffold. --}}
            @extends(\$layout ?? 'layouts.wp')

            @section('title')
                {$label}
            @endsection

            @section('content')
                <h1>{$label}</h1>

                @include('components.wp.post-list', ['posts' => \$posts])
            @endsection

            BLADE;
    }

    private function term(FrontendItem $term): string
    {
        $from = $this->from($term->template);

        return <<<BLADE
            {{-- The archive of a term of "{$term->subject}"{$from}. Generated by php artisan wp:scaffold. --}}
            @extends(\$layout ?? 'layouts.wp')

            @section('title', \$term->name)

            @section('content')
                <h1>{{ \$term->name }}</h1>

                @include('components.wp.post-list', ['posts' => \$posts])
            @endsection

            BLADE;
    }

    private function author(FrontendItem $author): string
    {
        $from = $this->from($author->template);
        $attribute = $this->frontend->authorNameAttribute();
        $title = $attribute === null ? "'Author'" : sprintf('$author->getAttribute(%s)', $this->php($attribute));
        $heading = $attribute === null ? 'Author' : '{{ '.$title.' }}';

        return <<<BLADE
            {{-- The archive of an author{$from}. Generated by php artisan wp:scaffold. --}}
            @extends(\$layout ?? 'layouts.wp')

            @section('title', {$title})

            @section('content')
                <h1>{$heading}</h1>

                @include('components.wp.post-list', ['posts' => \$posts])
            @endsection

            BLADE;
    }

    private function search(FrontendItem $search): string
    {
        $from = $this->from($search->template);

        return <<<BLADE
            {{-- Search results{$from}. Generated by php artisan wp:scaffold. --}}
            @extends(\$layout ?? 'layouts.wp')

            @section('title', 'Search')

            @section('content')
                <form action="{{ route('wp.search') }}" method="get">
                    <input type="search" name="q" value="{{ \$query }}">
                    <button type="submit">Search</button>
                </form>

                @include('components.wp.post-list', ['posts' => \$posts])
            @endsection

            BLADE;
    }

    private function notFound(FrontendItem $notFound): string
    {
        $from = $this->from($notFound->template);

        return <<<BLADE
            {{-- Page not found{$from}. Generated by php artisan wp:scaffold. --}}
            @extends('layouts.wp')

            @section('title', 'Page not found')

            @section('content')
                <h1>Page not found</h1>

                <p><a href="{{ url('/') }}">Go to the front page</a></p>
            @endsection

            BLADE;
    }

    private function templateLayout(FrontendItem $layout): string
    {
        $from = $this->from($layout->template);
        $class = 'template-'.basename(str_replace('.', '/', $layout->view));

        return <<<BLADE
            {{-- The layout of the page template "{$this->comment($this->templateName($layout))}"{$from}, for the posts whose
                 posts.template is "{$this->comment((string) $layout->subject)}". Generated by php artisan wp:scaffold. --}}
            @extends('layouts.wp')

            @section('main-class', '{$class}')

            BLADE;
    }

    private function blockView(string $name, int $count): string
    {
        $times = $count === 1 ? 'once' : $count.' times';

        if (! str_starts_with($name, 'elementor/')) {
            return <<<BLADE
                {{-- The block "{$name}", used {$times}. Generated by php artisan wp:scaffold.
                     \$content is its stored HTML and \$attrs its attributes; replace this with your markup. --}}
                {!! \$content !!}

                BLADE;
        }

        $widget = substr($name, 10);
        $attributes = array_filter(
            $this->blockAttributes[$name] ?? [],
            fn (string $key): bool => preg_match(self::ATTRIBUTE, $key) === 1,
            ARRAY_FILTER_USE_KEY,
        );
        $known = $attributes === [] ? 'its settings' : 'its settings: '.implode(', ', array_map(
            fn (string $key, string $kind): string => $kind === 'text' || $kind === 'value' ? $key : $key.' ('.$kind.')',
            array_keys($attributes),
            $attributes,
        ));
        $fields = '';

        foreach ($attributes as $key => $kind) {
            if ($kind === 'text') {
                $fields .= sprintf("    <div class=\"%s\">{{ \$attrs[%s] ?? '' }}</div>\n", $key, $this->php($key));
            }
        }

        if ($fields === '') {
            $fields = <<<'BLADE'
                    @foreach ($attrs as $key => $value)
                        @if (is_string($value) && $value !== '')
                            <div class="{{ $key }}">{{ $value }}</div>
                        @endif
                    @endforeach

                BLADE;
        }

        return <<<BLADE
            {{-- The Elementor widget "{$widget}", used {$times}. Generated by php artisan wp:scaffold.
                 \$attrs holds {$known}. Replace this with your markup. Text is printed escaped;
                 print fields whose HTML you trust with {!! !!} instead. --}}
            <div class="elementor-widget-{$widget}">
            {$fields}</div>

            BLADE;
    }

    private function blockViewPath(string $name): string
    {
        $path = str_starts_with($name, 'core/') ? substr($name, 5) : $name;

        return 'resources/views/vendor/wp-migrator/blocks/'.$path.'.blade.php';
    }

    private function templateName(FrontendItem $layout): string
    {
        return (string) preg_replace('/^Page template "(.*)"$/', '$1', $layout->label);
    }

    private function from(?Template $template): string
    {
        if ($template === null) {
            return '';
        }

        return ', from '.$this->comment($template->path).($template->source === 'child' ? ' of the child theme' : ' of the theme');
    }

    /**
     * @param  array<array-key, string|int>  $values
     */
    private function export(array $values): string
    {
        if ($values === []) {
            return '[]';
        }

        $lines = [];

        foreach ($values as $key => $value) {
            $lines[] = '        '.var_export($key, true).' => '.var_export($value, true).',';
        }

        return "[\n".implode("\n", $lines)."\n    ]";
    }

    /**
     * @param  list<string>  $lines
     */
    private function join(array $lines, string $indent): string
    {
        return implode("\n".$indent, $lines);
    }

    /**
     * The menu locations of the theme whose names can be used in a view.
     *
     * @return list<string>
     */
    private function locations(): array
    {
        return array_values(array_filter(
            array_map(strval(...), array_keys($this->frontend->theme->menuLocations)),
            fn (string $location): bool => $this->usable($location, self::LOCATION, 'menu location'),
        ));
    }

    /**
     * A PHP string literal.
     */
    private function php(string $value): string
    {
        return var_export($value, true);
    }

    /**
     * Text in a Blade view: HTML entities, also for the characters Blade
     * would compile, so nothing from the old site turns into code.
     */
    private function text(string $value): string
    {
        return strtr(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), ['{' => '&#123;', '}' => '&#125;', '@' => '&#64;']);
    }

    /**
     * Text inside a Blade comment, which must not end the comment.
     */
    private function comment(string $value): string
    {
        return str_replace('--}}', '- -}}', $this->shown($value));
    }

    /**
     * A name as it may be shown: without control characters, and short.
     */
    private function shown(string $value): string
    {
        return mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value), 0, 120);
    }
}
