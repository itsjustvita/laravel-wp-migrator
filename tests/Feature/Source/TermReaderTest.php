<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;
use ItsJustVita\WpMigrator\Source\Readers\TermFilter;
use ItsJustVita\WpMigrator\Source\Readers\TermReader;

it('reads terms parents first with their meta', function () {
    $site = $this->source();
    $parent = $site->term('category', 'Parent', ['description' => 'Top level']);
    $child = $site->term('category', 'Child', ['parent' => $parent]);
    $grandchild = $site->term('category', 'Grandchild', ['parent' => $child]);
    $tag = $site->term('post_tag', 'php');
    $site->termMeta($child, 'color', 'red');

    $terms = app(TermReader::class)->all(new TermFilter);

    expect(array_map(fn (SourceTerm $term) => $term->id, $terms))->toBe([$parent, $tag, $child, $grandchild]);

    $childTerm = $terms[2];

    expect($childTerm->taxonomy)->toBe('category')
        ->and($childTerm->name)->toBe('Child')
        ->and($childTerm->slug)->toBe('child')
        ->and($childTerm->parentId)->toBe($parent)
        ->and($childTerm->taxonomyId)->toBeInt()
        ->and($childTerm->meta->get('color'))->toBe('red')
        ->and($terms[0]->description)->toBe('Top level')
        ->and($terms[0]->parentId)->toBeNull();
});

it('skips menu and other internal taxonomies and filters by taxonomy', function () {
    $site = $this->source();
    $category = $site->term('category', 'News');
    $tag = $site->term('post_tag', 'php');
    $site->term('nav_menu', 'Main');
    $site->term('link_category', 'Links');
    $site->term('wp_theme', 'twentytwentyfour');

    $reader = app(TermReader::class);
    $ids = fn (TermFilter $filter) => array_map(fn (SourceTerm $term) => $term->id, $reader->all($filter));

    expect($ids(new TermFilter))->toBe([$category, $tag])
        ->and($ids(new TermFilter(taxonomies: ['post_tag'])))->toBe([$tag])
        ->and($reader->count(new TermFilter))->toBe(2);
});
