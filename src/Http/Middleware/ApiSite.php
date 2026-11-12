<?php

namespace Facilo\AltLingo\Http\Middleware;

use Closure;
use Facilo\AltLingo\AltLingo;
use Statamic\Facades\Site;

/**
 * Lets REST calls ask for one language: /api/assets/...?site=en
 */
class ApiSite
{
    public function handle($request, Closure $next)
    {
        $site = $request->query('site');

        if (! is_string($site) || ! Site::get($site)) {
            return $next($request);
        }

        $altlingo = app(AltLingo::class);
        $altlingo->setSiteOverride($site);

        try {
            return $next($request);
        } finally {
            $altlingo->setSiteOverride(null);
        }
    }
}
