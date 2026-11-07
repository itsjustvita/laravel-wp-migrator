<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use ItsJustVita\WpMigrator\Http\Middleware\RedirectMissingPages;
use ItsJustVita\WpMigrator\Models\Redirect;

beforeEach(function () {
    $this->app->make(Kernel::class)->pushMiddleware(RedirectMissingPages::class);
    Route::get('/', fn () => 'home');
    Route::get('/blog/hello', fn () => 'hello');
    Route::post('/form', fn () => 'posted');
    Route::get('/missing-model', fn () => abort(404));
    Route::get('/forbidden', fn () => abort(403));
    Route::get('/broken', fn () => abort(500));

    Redirect::create(['from_path' => '/2026/hello', 'to_path' => '/blog/hello']);
    Redirect::create(['from_path' => '/?p=12', 'to_path' => '/blog/hello']);
    Redirect::create(['from_path' => '/temporary', 'to_path' => '/blog/hello', 'status_code' => 302]);
    Redirect::create(['from_path' => '/grüße', 'to_path' => '/blog/hello']);
    Redirect::create(['from_path' => '/missing-model', 'to_path' => '/blog/hello']);
    Redirect::create(['from_path' => '/forbidden', 'to_path' => '/blog/hello']);
    Redirect::create(['from_path' => '/broken', 'to_path' => '/blog/hello']);
});

it('redirects a missing page and counts the hit', function () {
    $this->get('/2026/hello')->assertStatus(301)->assertRedirect('/blog/hello');

    $redirect = Redirect::query()->where('from_path', '/2026/hello')->sole();

    expect($redirect->hits)->toBe(1)
        ->and($redirect->last_hit_at)->not->toBeNull();
});

it('matches regardless of trailing slash, case and encoding', function (string $path) {
    $this->get($path)->assertStatus(301)->assertRedirect('/blog/hello');
})->with(['/2026/hello/', '/2026/Hello', '/gr%C3%BC%C3%9Fe/']);

it('keeps the query string', function () {
    $this->get('/2026/hello?utm_source=x')->assertRedirect('/blog/hello?utm_source=x');
});

it('uses the stored status code', function () {
    $this->get('/temporary')->assertStatus(302);
});

it('redirects a 404 raised by a route', function () {
    $this->get('/missing-model')->assertStatus(301);
});

it('redirects query urls even though the front page exists', function () {
    $this->get('/?p=12')->assertStatus(301)->assertRedirect('/blog/hello');
    $this->get('/?p=12&utm_source=x')->assertRedirect('/blog/hello?utm_source=x');
});

it('leaves everything else alone', function () {
    $this->get('/')->assertOk()->assertSee('home');
    $this->get('/?p=999')->assertOk()->assertSee('home');
    $this->get('/blog/hello')->assertOk();
    $this->get('/nowhere')->assertNotFound();
    $this->post('/2026/hello')->assertNotFound();
});

it('does not break 404 pages when the table is missing', function () {
    Schema::drop('redirects');

    $this->get('/2026/hello')->assertNotFound();
    $this->get('/?p=12')->assertOk();
});

it('only steps in for pages that do not exist', function () {
    $this->get('/forbidden')->assertForbidden();
    $this->get('/broken')->assertStatus(500);
});
