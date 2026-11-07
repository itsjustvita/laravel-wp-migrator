<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Facades;

use Illuminate\Support\Facades\Facade;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;
use ItsJustVita\WpMigrator\Mapping\MediaMapper;
use ItsJustVita\WpMigrator\Mapping\PostMapper;
use ItsJustVita\WpMigrator\Mapping\TermMapper;
use ItsJustVita\WpMigrator\Mapping\UserMapper;

/**
 * @method static \ItsJustVita\WpMigrator\WpMigrator mapPostType(string $postType, class-string<PostMapper> $mapper)
 * @method static \ItsJustVita\WpMigrator\WpMigrator mapTaxonomy(string $taxonomy, class-string<TermMapper> $mapper)
 * @method static \ItsJustVita\WpMigrator\WpMigrator mapUsers(class-string<UserMapper> $mapper)
 * @method static \ItsJustVita\WpMigrator\WpMigrator mapMedia(class-string<MediaMapper> $mapper)
 * @method static \ItsJustVita\WpMigrator\WpMigrator mapShortcode(string $tag, class-string<ShortcodeHandler> $handler)
 * @method static bool hasShortcode(string $tag)
 * @method static ShortcodeHandler|null shortcodeHandler(string $tag)
 * @method static \ItsJustVita\WpMigrator\WpMigrator renderBlockUsing(string $block, string $view)
 * @method static string|null blockView(string $block)
 * @method static PostMapper postMapper(string $postType)
 * @method static TermMapper termMapper(string $taxonomy)
 * @method static UserMapper userMapper()
 * @method static MediaMapper mediaMapper()
 *
 * @see \ItsJustVita\WpMigrator\WpMigrator
 */
class WpMigrator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ItsJustVita\WpMigrator\WpMigrator::class;
    }
}
