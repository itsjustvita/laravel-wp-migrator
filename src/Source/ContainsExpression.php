<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * "<column> like ? escape '!'" with the column wrapped by the grammar of
 * the connection the query runs on.
 */
final readonly class ContainsExpression implements Expression
{
    public function __construct(private string $column) {}

    public function getValue(Grammar $grammar): string
    {
        return $grammar->wrap($this->column)." like ? escape '!'";
    }
}
