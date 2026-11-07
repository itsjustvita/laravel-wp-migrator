<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Detectors;

use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Detection\Finding;
use ItsJustVita\WpMigrator\Source\WpDatabase;

final class WooCommerceDetector extends PostDetector
{
    private const array ORDER_TABLES = ['wc_orders', 'woocommerce_order_items'];

    protected function key(): string
    {
        return 'woocommerce';
    }

    protected function label(): string
    {
        return 'WooCommerce';
    }

    protected function affectedPosts(WpDatabase $db): Builder
    {
        return $db->posts()->whereIn('post_type', ['product', 'product_variation']);
    }

    public function detect(WpDatabase $db): Finding
    {
        $products = parent::detect($db);

        $tables = array_values(array_filter(
            array_map(fn (string $table): string => $db->tableName($table), self::ORDER_TABLES),
            fn (string $table): bool => $db->connection()->getSchemaBuilder()->hasTable($table),
        ));

        if ($tables === []) {
            return $products;
        }

        return Finding::posts(
            $this->key(),
            $this->label(),
            $products->count,
            $products->sampleIds,
            'Order tables found: '.implode(', ', $tables),
        );
    }
}
