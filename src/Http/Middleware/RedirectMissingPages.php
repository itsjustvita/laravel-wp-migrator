<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use ItsJustVita\WpMigrator\Models\Redirect;
use ItsJustVita\WpMigrator\Redirects\RedirectPath;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers requests for old URLs with their redirect. Register it as global
 * middleware, so it also sees requests no route matches.
 *
 * Paths are only looked up once the application answered with 404. Query
 * URLs such as /?p=12 hit the front page, which exists, so they are looked
 * up before the request is handled.
 */
class RedirectMissingPages
{
    private const array QUERY_KEYS = ['p', 'page_id', 'attachment_id', 'cat', 'tag', 'author'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        if ($request->path() === '/') {
            foreach (self::QUERY_KEYS as $key) {
                $value = $request->query($key);

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $redirect = $this->find('/?'.$key.'='.$value);

                if ($redirect !== null) {
                    return $this->redirect($redirect, $request, $key);
                }
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        $redirect = $this->find('/'.$request->path());

        return $redirect === null ? $response : $this->redirect($redirect, $request);
    }

    private function find(string $path): ?Redirect
    {
        try {
            return Redirect::query()->where('from_path', RedirectPath::normalise($path))->first();
        } catch (QueryException) {
            // Without the redirects table the application keeps its 404 page.
            return null;
        }
    }

    private function redirect(Redirect $redirect, Request $request, ?string $usedKey = null): RedirectResponse
    {
        $redirect->increment('hits', 1, ['last_hit_at' => now()]);

        $target = $redirect->to_path;
        $query = $request->query();

        if ($usedKey !== null) {
            unset($query[$usedKey]);
        }

        if ($query !== [] && ! str_contains($target, '?')) {
            $target .= '?'.http_build_query($query);
        }

        return new RedirectResponse(url($target), $redirect->status_code);
    }
}
