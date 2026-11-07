<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use ItsJustVita\WpMigrator\Models\Redirect;

beforeEach(function () {
    Redirect::create(['from_path' => '/2026/hello', 'to_path' => '/blog/hello', 'hits' => 3]);
    Redirect::create(['from_path' => '/?p=12', 'to_path' => '/blog/hello']);
    Redirect::create(['from_path' => '/grüße welt', 'to_path' => 'https://new.example.com/greet', 'status_code' => 302]);
});

function export(array $options): string
{
    Artisan::call('wp:redirects:export', $options);

    return Artisan::output();
}

it('exports csv', function () {
    expect(export(['--format' => 'csv']))->toBe(
        "from_path,to_path,status_code,hits\n"
        ."/2026/hello,/blog/hello,301,3\n"
        ."/?p=12,/blog/hello,301,0\n"
        ."\"/grüße welt\",https://new.example.com/greet,302,0\n"
    );
});

it('exports an nginx map per status code', function () {
    $output = export(['--format' => 'nginx']);

    expect($output)->toContain('map $request_uri $wp_migrator_redirect_301 {')
        ->toContain('    default "";')
        ->toContain('    "/2026/hello" "/blog/hello";')
        ->toContain('    "/2026/hello/" "/blog/hello";')
        ->toContain('    "/?p=12" "/blog/hello";')
        ->toContain('map $request_uri $wp_migrator_redirect_302 {')
        ->toContain('    "/gr%C3%BC%C3%9Fe%20welt" "https://new.example.com/greet";')
        ->toContain('if ($wp_migrator_redirect_301) { return 301 $wp_migrator_redirect_301; }')
        ->not->toContain('"/?p=12/"');
});

it('exports apache rewrite rules', function () {
    $output = export(['--format' => 'apache']);

    expect($output)->toContain('RewriteEngine On')
        ->toContain('RewriteRule "^/?2026/hello/?$" "/blog/hello" [R=301,L,NE,NC]')
        ->toContain("RewriteCond %{QUERY_STRING} \"^p=12$\"\nRewriteRule \"^/?$\" \"/blog/hello?\" [R=301,L,NE]")
        ->toContain('RewriteRule "^/?grüße welt/?$" "https://new.example.com/greet" [R=302,L,NE,NC]');
});

it('escapes characters with a meaning in rewrite patterns', function () {
    Redirect::create(['from_path' => '/a.b+c(d)[e]', 'to_path' => '/x']);

    expect(export(['--format' => 'apache']))->toContain('RewriteRule "^/?a\.b\+c\(d\)\[e\]/?$" "/x" [R=301,L,NE,NC]');
});

it('writes to a file', function () {
    $file = sys_get_temp_dir().'/wp-migrator-redirects-'.uniqid().'.csv';

    $this->artisan('wp:redirects:export', ['--format' => 'csv', '--output' => $file])
        ->expectsOutputToContain('3 redirects written to')
        ->assertSuccessful();

    expect(file_get_contents($file))->toStartWith("from_path,to_path,status_code,hits\n");

    unlink($file);
});

it('rejects an unknown format', function () {
    $this->artisan('wp:redirects:export', ['--format' => 'iis'])
        ->expectsOutputToContain('Unknown format "iis"')
        ->assertFailed();
});

it('leaves out redirects that would break out of the server configuration', function (string $format) {
    Redirect::create(['from_path' => "/evil\nRewriteRule .* http://attacker.test [R]", 'to_path' => '/x']);
    Redirect::create(['from_path' => '/evil-target', 'to_path' => "/x\"\nreturn 200 \"owned"]);
    Redirect::create(['from_path' => '/back\\slash', 'to_path' => '/x']);

    $this->artisan('wp:redirects:export', ['--format' => $format])
        ->expectsOutputToContain('3 redirects left out')
        ->assertSuccessful();

    $output = export(['--format' => $format]);

    expect($output)->not->toContain('attacker.test')
        ->not->toContain('owned')
        ->not->toContain('/back')
        ->toContain('/blog/hello');
})->with(['nginx', 'apache']);

it('keeps percent signs in apache targets literal', function () {
    Redirect::create(['from_path' => '/strasse', 'to_path' => '/stra%C3%9Fe%20neu']);

    expect(export(['--format' => 'apache']))->toContain('RewriteRule "^/?strasse/?$" "/stra\\%C3\\%9Fe\\%20neu" [R=301,L,NE,NC]');
});

it('encodes the query of nginx keys', function () {
    Redirect::create(['from_path' => '/?tag=c++ und mehr', 'to_path' => '/x']);

    expect(export(['--format' => 'nginx']))->toContain('    "/?tag=c%2B%2B%20und%20mehr" "/x";');
});
