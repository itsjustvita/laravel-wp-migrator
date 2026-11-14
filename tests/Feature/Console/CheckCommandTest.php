<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use ItsJustVita\WpMigrator\Source\SourceStats;

it('fails when the source connection cannot be opened', function () {
    config()->set('database.connections.wordpress', ['driver' => 'sqlite', 'database' => '/nonexistent/wordpress.sqlite']);
    app('db')->purge('wordpress');

    $this->artisan('wp:check')
        ->expectsOutputToContain('Connection')
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('fails when the tables with the configured prefix are missing', function () {
    $this->source();
    config()->set('wp-migrator.source.table_prefix', 'other_');

    $this->artisan('wp:check')
        ->expectsOutputToContain('Table other_posts not found. Check wp-migrator.source.table_prefix')
        ->assertFailed();
});

it('prints counts per post type, taxonomy, users and media', function () {
    $site = $this->source();
    $site->option('home', 'https://old.example.com');
    $site->post(['post_title' => 'A']);
    $site->post(['post_title' => 'B']);
    $site->post(['post_title' => 'C', 'post_status' => 'draft']);
    $site->page(['post_title' => 'About']);
    $site->post(['post_type' => 'event']);
    $site->attachment();
    $site->term('category', 'News');
    $site->term('post_tag', 'php');
    $site->user(['user_login' => 'alice']);

    $this->artisan('wp:check')
        ->expectsOutputToContain('https://old.example.com')
        ->expectsOutputToContain('post (publish 2, draft 1)')
        ->expectsOutputToContain('page')
        ->expectsOutputToContain('event')
        ->expectsOutputToContain('category')
        ->expectsOutputToContain('post_tag')
        ->expectsOutputToContain('Users')
        ->expectsOutputToContain('Media')
        ->assertSuccessful();

    Artisan::call('wp:check');

    expect(Artisan::output())->not->toContain('Administrators');
});

it('warns about a missing uploads path without failing', function () {
    $this->source()->option('home', 'https://old.example.com');
    config()->set('wp-migrator.source.uploads_path', '/nonexistent/uploads');

    $this->artisan('wp:check')
        ->expectsOutputToContain('not readable /nonexistent/uploads')
        ->assertSuccessful();
});

it('warns when neither an uploads path nor a site url is available', function () {
    $this->source();

    $this->artisan('wp:check')
        ->expectsOutputToContain('Uploads')
        ->expectsOutputToContain('site_url')
        ->assertSuccessful();
});

it('confirms a readable uploads path', function () {
    $this->source()->option('home', 'https://old.example.com');
    $dir = sys_get_temp_dir().'/wp-migrator-uploads-'.uniqid();
    mkdir($dir);
    config()->set('wp-migrator.source.uploads_path', $dir);

    try {
        $this->artisan('wp:check')
            ->expectsOutputToContain('readable '.$dir)
            ->assertSuccessful();
    } finally {
        rmdir($dir);
    }
});

it('lists unsupported content with affected post ids', function () {
    $site = $this->source();
    $site->option('home', 'https://old.example.com');
    $landing = $site->page(['post_title' => 'Landing']);
    $site->postMeta($landing, '_elementor_data', '[{"id":"x"}]');
    $divi = $site->page(['post_content' => '[et_pb_section][/et_pb_section]']);

    $this->artisan('wp:check')
        ->expectsOutputToContain("1 post (ID {$landing})")
        ->expectsOutputToContain("1 post (ID {$divi})")
        ->expectsOutputToContain('2 kinds of unsupported content found')
        ->assertSuccessful();
});

it('lists the administrators to review and marks excluded ones', function () {
    $site = $this->source();
    $site->option('home', 'https://old.example.com');
    $owner = $site->user(['user_login' => 'owner', 'user_email' => 'owner@example.com', 'user_registered' => '2024-02-01 10:00:00']);
    $site->userMeta($owner, 'wp_capabilities', serialize(['administrator' => true]));
    $intruder = $site->user(['user_login' => 'intruder', 'user_email' => 'x@evil.example', 'user_registered' => '2026-05-04 03:12:00']);
    $site->userMeta($intruder, 'wp_capabilities', serialize(['administrator' => true]));
    $planted = $site->user(['user_login' => 'planted', 'user_email' => 'p@evil.example', 'user_registered' => '2025-05-05 00:00:00']);
    $site->userMeta($planted, 'wp_capabilities', serialize(['administrator' => true]));
    $editor = $site->user(['user_login' => 'writer']);
    $site->userMeta($editor, 'wp_capabilities', serialize(['editor' => true]));
    config()->set('wp-migrator.users.excluded', ['planted']);

    expect(Artisan::call('wp:check'))->toBe(0);

    $output = Artisan::output();

    expect($output)
        ->toMatch('/Administrators.*review before the import/')
        ->toMatch('/owner \\(owner@example\.com\) \.+ registered 2024-02-01/')
        ->toMatch('/intruder \\(x@evil\.example\) \.+ registered 2026-05-04/')
        ->toMatch('/planted \\(p@evil\.example\) \.+ excluded/')
        ->toContain("A hacked site can hold an attacker's account")
        ->toContain('wp-migrator.users.excluded')
        ->not->toContain('writer (')
        ->not->toContain('2025-05-05');
});

it('reads the administrators with their registration date', function () {
    $site = $this->source();
    $owner = $site->user(['user_login' => 'owner', 'user_email' => 'owner@example.com', 'user_registered' => '2024-02-01 10:00:00']);
    $site->userMeta($owner, 'wp_capabilities', serialize(['administrator' => true]));
    $old = $site->user(['user_login' => 'old', 'user_registered' => '0000-00-00 00:00:00']);
    $site->userMeta($old, 'wp_capabilities', serialize(['administrator' => true, 'editor' => true]));
    $editor = $site->user(['user_login' => 'writer']);
    $site->userMeta($editor, 'wp_capabilities', serialize(['editor' => true]));
    $site->userMeta($editor, 'other_capabilities', serialize(['administrator' => true]));
    $translator = $site->user(['user_login' => 'translator']);
    $site->userMeta($translator, 'wp_capabilities', serialize(['translation_administrator' => true]));

    expect(app(SourceStats::class)->administrators())->toBe([
        ['id' => $owner, 'login' => 'owner', 'email' => 'owner@example.com', 'registered' => '2024-02-01'],
        ['id' => $old, 'login' => 'old', 'email' => 'old@example.com', 'registered' => null],
    ]);
});
