<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\AudioHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\CaptionHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\EmbedHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\GalleryHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\Handlers\VideoHandler;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;
use ItsJustVita\WpMigrator\Mapping\CommentMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultCommentMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultMediaMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultMenuItemMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultMenuMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultNavigationMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultPostMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultTermMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultUserMapper;
use ItsJustVita\WpMigrator\Mapping\MediaMapper;
use ItsJustVita\WpMigrator\Mapping\MenuItemMapper;
use ItsJustVita\WpMigrator\Mapping\MenuMapper;
use ItsJustVita\WpMigrator\Mapping\NavigationMapper;
use ItsJustVita\WpMigrator\Mapping\PostMapper;
use ItsJustVita\WpMigrator\Mapping\TermMapper;
use ItsJustVita\WpMigrator\Mapping\UserMapper;

/**
 * Registry of the mappers used by the import. Register custom mappers in a
 * service provider's boot method:
 *
 *     WpMigrator::mapPostType('event', EventMapper::class);
 */
class WpMigrator
{
    /** @var array<string, class-string<PostMapper>> */
    private array $postMappers = [];

    /** @var array<string, class-string<TermMapper>> */
    private array $termMappers = [];

    /** @var class-string<UserMapper> */
    private string $userMapper = DefaultUserMapper::class;

    /** @var class-string<MediaMapper> */
    private string $mediaMapper = DefaultMediaMapper::class;

    /** @var class-string<MenuMapper> */
    private string $menuMapper = DefaultMenuMapper::class;

    /** @var class-string<MenuItemMapper> */
    private string $menuItemMapper = DefaultMenuItemMapper::class;

    /** @var class-string<NavigationMapper> */
    private string $navigationMapper = DefaultNavigationMapper::class;

    /** @var class-string<CommentMapper> */
    private string $commentMapper = DefaultCommentMapper::class;

    /** @var array<string, class-string<ShortcodeHandler>> */
    private array $shortcodes = [
        'caption' => CaptionHandler::class,
        'wp_caption' => CaptionHandler::class,
        'gallery' => GalleryHandler::class,
        'embed' => EmbedHandler::class,
        'video' => VideoHandler::class,
        'audio' => AudioHandler::class,
    ];

    /** @var array<string, string> */
    private array $blockViews = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<PostMapper>  $mapper
     */
    public function mapPostType(string $postType, string $mapper): static
    {
        $this->postMappers[$postType] = $this->ensureImplements($mapper, PostMapper::class);
        unset($this->instances['post:'.$postType]);

        return $this;
    }

    /**
     * @param  class-string<TermMapper>  $mapper
     */
    public function mapTaxonomy(string $taxonomy, string $mapper): static
    {
        $this->termMappers[$taxonomy] = $this->ensureImplements($mapper, TermMapper::class);
        unset($this->instances['term:'.$taxonomy]);

        return $this;
    }

    /**
     * @param  class-string<UserMapper>  $mapper
     */
    public function mapUsers(string $mapper): static
    {
        $this->userMapper = $this->ensureImplements($mapper, UserMapper::class);
        unset($this->instances['user']);

        return $this;
    }

    /**
     * @param  class-string<MediaMapper>  $mapper
     */
    public function mapMedia(string $mapper): static
    {
        $this->mediaMapper = $this->ensureImplements($mapper, MediaMapper::class);
        unset($this->instances['media']);

        return $this;
    }

    /**
     * @param  class-string<MenuMapper>  $mapper
     */
    public function mapMenus(string $mapper): static
    {
        $this->menuMapper = $this->ensureImplements($mapper, MenuMapper::class);
        unset($this->instances['menu']);

        return $this;
    }

    /**
     * @param  class-string<MenuItemMapper>  $mapper
     */
    public function mapMenuItems(string $mapper): static
    {
        $this->menuItemMapper = $this->ensureImplements($mapper, MenuItemMapper::class);
        unset($this->instances['menu item']);

        return $this;
    }

    /**
     * @param  class-string<NavigationMapper>  $mapper
     */
    public function mapNavigation(string $mapper): static
    {
        $this->navigationMapper = $this->ensureImplements($mapper, NavigationMapper::class);
        unset($this->instances['navigation']);

        return $this;
    }

    /**
     * @param  class-string<CommentMapper>  $mapper
     */
    public function mapComments(string $mapper): static
    {
        $this->commentMapper = $this->ensureImplements($mapper, CommentMapper::class);
        unset($this->instances['comment']);

        return $this;
    }

    public function menuMapper(): MenuMapper
    {
        return $this->instance('menu', $this->menuMapper, MenuMapper::class);
    }

    public function menuItemMapper(): MenuItemMapper
    {
        return $this->instance('menu item', $this->menuItemMapper, MenuItemMapper::class);
    }

    public function navigationMapper(): NavigationMapper
    {
        return $this->instance('navigation', $this->navigationMapper, NavigationMapper::class);
    }

    public function commentMapper(): CommentMapper
    {
        return $this->instance('comment', $this->commentMapper, CommentMapper::class);
    }

    /**
     * @param  class-string<ShortcodeHandler>  $handler
     */
    public function mapShortcode(string $tag, string $handler): static
    {
        $this->shortcodes[$tag] = $this->ensureImplements($handler, ShortcodeHandler::class);
        unset($this->instances['shortcode:'.$tag]);

        return $this;
    }

    public function hasShortcode(string $tag): bool
    {
        return isset($this->shortcodes[$tag]);
    }

    public function shortcodeHandler(string $tag): ?ShortcodeHandler
    {
        if (! isset($this->shortcodes[$tag])) {
            return null;
        }

        return $this->instance('shortcode:'.$tag, $this->shortcodes[$tag], ShortcodeHandler::class);
    }

    /**
     * Renders blocks of this name with the given view instead of the shipped one.
     */
    public function renderBlockUsing(string $block, string $view): static
    {
        $this->blockViews[$block] = $view;

        return $this;
    }

    public function blockView(string $block): ?string
    {
        return $this->blockViews[$block] ?? null;
    }

    public function postMapper(string $postType): PostMapper
    {
        return $this->instance('post:'.$postType, $this->postMappers[$postType] ?? DefaultPostMapper::class, PostMapper::class);
    }

    public function termMapper(string $taxonomy): TermMapper
    {
        return $this->instance('term:'.$taxonomy, $this->termMappers[$taxonomy] ?? DefaultTermMapper::class, TermMapper::class);
    }

    public function userMapper(): UserMapper
    {
        return $this->instance('user', $this->userMapper, UserMapper::class);
    }

    public function mediaMapper(): MediaMapper
    {
        return $this->instance('media', $this->mediaMapper, MediaMapper::class);
    }

    /**
     * @template T of object
     *
     * @param  class-string  $class
     * @param  class-string<T>  $contract
     * @return T
     */
    private function instance(string $key, string $class, string $contract): object
    {
        $instance = $this->instances[$key] ??= $this->container->make($class);

        if (! $instance instanceof $contract) {
            throw new InvalidArgumentException(sprintf('%s must implement %s.', $class, $contract));
        }

        return $instance;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return class-string<T>
     */
    private function ensureImplements(string $class, string $contract): string
    {
        if (! is_subclass_of($class, $contract)) {
            throw new InvalidArgumentException(sprintf('%s must implement %s.', $class, $contract));
        }

        return $class;
    }
}
