<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\Concerns\UniqueMenuSlug;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Source\Dto\SourceMenu;

class DefaultMenuMapper implements MenuMapper
{
    use UniqueMenuSlug;

    public function map(SourceMenu $menu, ImportContext $context): ?Model
    {
        $model = $context->existing(SourceType::Term, $menu->id, Menu::class) ?? new Menu;
        $slug = $menu->slug !== '' ? $menu->slug : Str::slug($menu->name);

        $model->fill([
            'name' => $menu->name,
            'slug' => $this->uniqueSlug($model, $slug !== '' ? $slug : 'menu', $menu->id),
            'locations' => $menu->locations === [] ? null : $menu->locations,
        ])->save();

        return $model;
    }
}
