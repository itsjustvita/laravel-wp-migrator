<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Mapping\DefaultTermMapper;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;

function sourceTerm(int $id, string $name, ?int $parentId = null, string $slug = ''): SourceTerm
{
    return new SourceTerm($id, $id, 'category', $name, $slug, '', $parentId, MetaBag::empty());
}

it('creates a term and resolves its parent through the import map', function () {
    $context = importContext('terms');
    $mapper = app(DefaultTermMapper::class);

    $parent = $mapper->map(sourceTerm(10, 'Parent', slug: 'parent'), $context);
    $context->map->record(SourceType::Term, 10, $parent, 'x');
    $child = $mapper->map(sourceTerm(11, 'Child', parentId: 10, slug: 'child'), $context);

    expect($child)->toBeInstanceOf(Term::class)
        ->and($child->taxonomy)->toBe('category')
        ->and($child->name)->toBe('Child')
        ->and($child->slug)->toBe('child')
        ->and($child->parent_id)->toBe($parent->id)
        ->and($context->report->unresolvedReferences())->toBe([]);
});

it('reports an unresolved parent and derives missing slugs from the name', function () {
    $context = importContext('terms');

    $term = app(DefaultTermMapper::class)->map(sourceTerm(11, 'Ohne Slug', parentId: 99), $context);

    expect($term->parent_id)->toBeNull()
        ->and($term->slug)->toBe('ohne-slug')
        ->and($context->report->unresolvedReferences())->toBe([
            ['importer' => 'terms', 'source_id' => 11, 'kind' => 'parent term', 'reference' => '99'],
        ]);
});

it('updates the term it created before', function () {
    $context = importContext('terms');
    $mapper = app(DefaultTermMapper::class);

    $first = $mapper->map(sourceTerm(10, 'Old name', slug: 'old'), $context);
    $context->map->record(SourceType::Term, 10, $first, 'x');
    $second = $mapper->map(sourceTerm(10, 'New name', slug: 'new'), $context);

    expect($second->is($first))->toBeTrue()
        ->and(Term::count())->toBe(1)
        ->and($second->name)->toBe('New name');
});
