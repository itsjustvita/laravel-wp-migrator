<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Import\ImportReport;

it('counts outcomes per importer', function () {
    $report = new ImportReport;
    $report->created('posts');
    $report->created('posts');
    $report->updated('posts');
    $report->unchanged('posts');
    $report->skipped('posts', 9, 'Mapper returned nothing');
    $report->failed('media', 7, new RuntimeException('disk full'));

    expect($report->counts())->toBe([
        'posts' => ['created' => 2, 'updated' => 1, 'unchanged' => 1, 'skipped' => 1, 'failed' => 0],
        'media' => ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 1],
    ])
        ->and($report->skippedItems())->toBe([['importer' => 'posts', 'source_id' => 9, 'reason' => 'Mapper returned nothing']])
        ->and($report->hasErrors())->toBeTrue()
        ->and($report->errors())->toHaveCount(1)
        ->and($report->errors()[0]['message'])->toContain('disk full');
});

it('collects warnings and unresolved references', function () {
    $report = new ImportReport;
    $report->warn('users', 3, 'Linked to existing user');
    $report->unresolved('posts', 12, 'parent post', 5);

    expect($report->warnings())->toBe([['importer' => 'users', 'source_id' => 3, 'message' => 'Linked to existing user']])
        ->and($report->unresolvedReferences())->toBe([['importer' => 'posts', 'source_id' => 12, 'kind' => 'parent post', 'reference' => '5']])
        ->and($report->hasErrors())->toBeFalse();
});

it('renders a markdown summary', function () {
    $report = new ImportReport;
    $report->created('posts');
    $report->warn('users', 3, 'Linked to existing user #1');
    $report->unresolved('posts', 12, 'featured media', 99);
    $report->failed('media', 7, new RuntimeException('disk full'));
    $report->finish();

    $markdown = $report->toMarkdown();

    expect($markdown)->toContain('# WP migration report')
        ->toContain('| posts | 1 | 0 | 0 | 0 | 0 |')
        ->toContain('## Warnings')
        ->toContain('users #3: Linked to existing user #1')
        ->toContain('## Unresolved references')
        ->toContain('posts #12: featured media 99')
        ->toContain('## Errors')
        ->toContain('media #7: disk full');
});

it('lists unknown shortcodes and blocks in the markdown', function () {
    $report = new ImportReport;
    $report->unknownShortcode('vc_row', 12);
    $report->unknownShortcode('vc_row', 13);
    $report->unknownBlock('acme/hero', 7);

    expect($report->toMarkdown())
        ->toContain('## Unknown shortcodes (1)')
        ->toContain('- [vc_row]: 2 occurrences, for example in posts 12, 13')
        ->toContain('## Blocks without a renderer (1)')
        ->toContain('- acme/hero: 1 occurrence, for example in post 7');
});
