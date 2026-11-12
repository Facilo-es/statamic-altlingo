<?php

namespace Facilo\AltLingo\Http\Controllers;

use Facilo\AltLingo\AltLingo;
use Facilo\AltLingo\Coverage\CoverageCache;
use Facilo\AltLingo\Resolver;
use Illuminate\Http\Request;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;
use Statamic\Facades\Site;
use Statamic\Facades\User;

trait Concerns
{
    protected function altlingo(): AltLingo
    {
        return app(AltLingo::class);
    }

    protected function proOnly(): void
    {
        abort_unless($this->altlingo()->config->isPro(), 404);
    }

    protected function canSite(string $site): bool
    {
        $user = User::current();

        return $user && in_array($site, $this->altlingo()->config->sites(), true)
            && (! Site::multiEnabled() || $user->can("access {$site} site"));
    }

    protected function authorizeSite(string $site): void
    {
        abort_unless($this->canSite($site), 403, __('altlingo::messages.no_site_access'));
    }

    protected function asset(string $id): AssetContract
    {
        $asset = Asset::find($id);
        abort_unless($asset && $this->altlingo()->isCovered($asset), 404);

        return $asset;
    }

    /**
     * Ids of the images matching the grid filters, read from the coverage cache (no asset loads).
     *
     * @return array<int, string>
     */
    protected function filteredIds(Request $request): array
    {
        $rows = app(CoverageCache::class)->allRows();
        $container = (string) $request->input('container', '');
        $folder = trim((string) $request->input('folder', ''), '/');
        $site = (string) $request->input('site', '');
        $status = (string) $request->input('status', '');
        $q = mb_strtolower(trim((string) $request->input('q', '')));
        $sites = $site !== '' ? [$site] : $this->altlingo()->config->sites();

        $ids = [];
        foreach ($rows as $id => $row) {
            [$c, $path] = explode('::', $id, 2);
            if ($container !== '' && $c !== $container) {
                continue;
            }
            if ($folder !== '' && ! str_starts_with($path, $folder.'/')) {
                continue;
            }
            if ($q !== '' && ! str_contains($row['q'], $q)) {
                continue;
            }
            if ($status !== '' && ! collect($sites)->contains(fn ($s) => ($row['s'][$s] ?? Resolver::MISSING) === $status)) {
                continue;
            }
            $ids[] = $id;
        }

        return $ids;
    }
}
