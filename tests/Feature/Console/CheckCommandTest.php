<?php

declare(strict_types=1);

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
