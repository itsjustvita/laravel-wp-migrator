<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Source\SourceStats;

it('counts posts per type and status, ignoring revisions and auto-drafts', function () {
    $site = $this->source();
    $site->post(['post_title' => 'A']);
    $site->post(['post_title' => 'B']);
    $site->post(['post_title' => 'C', 'post_status' => 'draft']);
    $site->page(['post_title' => 'About']);
    $site->attachment();
    $site->attachment();
    $site->post(['post_title' => 'Rev', 'post_type' => 'revision']);
    $site->post(['post_title' => 'Auto', 'post_status' => 'auto-draft']);

    $counts = app(SourceStats::class)->postCounts();

    expect($counts)->toBe([
        'post' => ['publish' => 2, 'draft' => 1],
        'attachment' => ['inherit' => 2],
        'page' => ['publish' => 1],
    ]);
});

it('counts terms per taxonomy', function () {
    $site = $this->source();
    $site->term('category', 'News');
    $site->term('category', 'Events');
    $site->term('post_tag', 'php');

    expect(app(SourceStats::class)->taxonomyCounts())->toBe([
        'category' => 2,
        'post_tag' => 1,
    ]);
});

it('counts users and media', function () {
    $site = $this->source();
    $site->user(['user_login' => 'alice']);
    $site->user(['user_login' => 'bob']);
    $site->attachment();
    $site->post();

    $stats = app(SourceStats::class);

    expect($stats->userCount())->toBe(2)
        ->and($stats->mediaCount())->toBe(1);
});
