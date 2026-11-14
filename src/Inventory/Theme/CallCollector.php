<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects calls to the given functions from a syntax tree.
 *
 * @internal
 */
final class CallCollector extends NodeVisitorAbstract
{
    /** @var list<FuncCall> */
    public array $calls = [];

    /**
     * @param  list<string>  $functions  lowercase function names
     */
    public function __construct(private readonly array $functions) {}

    public function enterNode(Node $node): null
    {
        if ($node instanceof FuncCall
            && $node->name instanceof Name
            && in_array(strtolower($node->name->toString()), $this->functions, true)) {
            $this->calls[] = $node;
        }

        return null;
    }
}
