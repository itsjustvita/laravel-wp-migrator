<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Menus\MenuLink;
use ItsJustVita\WpMigrator\Menus\MenuLinks;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenuItem;

class DefaultMenuItemMapper implements MenuItemMapper
{
    public function __construct(protected readonly MenuLinks $links) {}

    public function map(SourceMenuItem $item, ImportContext $context): ?Model
    {
        $menuId = $context->resolve(SourceType::Term, $item->menuId, $item->id, 'menu', Menu::class);

        if ($menuId === null) {
            $context->skip('its menu was not imported');

            return null;
        }

        $model = $context->existing(SourceType::Post, $item->id, MenuItem::class) ?? new MenuItem;

        $model->fill([
            'menu_id' => $menuId,
            'parent_id' => $context->resolve(SourceType::Post, $item->parentId, $item->id, 'parent menu item', MenuItem::class),
            'title' => $item->title !== '' ? $item->title : ($item->objectTitle ?? ''),
            'order' => max(0, $item->order),
            'target' => $item->target === '_blank' ? '_blank' : null,
            'classes' => $item->classes === [] ? null : $item->classes,
            ...$this->link($item, $context)->attributes(),
        ])->save();

        return $model;
    }

    protected function link(SourceMenuItem $item, ImportContext $context): MenuLink
    {
        return match (true) {
            $item->type === 'post_type' && $item->objectId !== null => $this->links->post($item->objectId, $item->id, $context),
            $item->type === 'taxonomy' && $item->objectId !== null => $this->links->term($item->objectId, $item->id, $context),
            $item->type === 'post_type_archive' => $this->archive($item, $context),
            default => $this->links->custom($item->url, $item->id, $context),
        };
    }

    private function archive(SourceMenuItem $item, ImportContext $context): MenuLink
    {
        $context->warn($item->id, sprintf('Menu item "%s" links to the archive of the post type "%s", which has no new URL; set it by hand.', $item->title, $item->object));

        return new MenuLink(null);
    }
}
