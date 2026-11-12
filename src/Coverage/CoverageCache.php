<?php

namespace Facilo\AltLingo\Coverage;

use Facilo\AltLingo\AltLingo;
use Facilo\AltLingo\Resolver;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\AssetContainer;

/**
 * One small row per image (status per site and a search string), kept in Laravel's cache
 * and updated when an asset is saved or deleted, so the page and the widget stay fast.
 */
class CoverageCache
{
    private const KEY = 'altlingo.coverage.';

    public function __construct(private AltLingo $altlingo)
    {
    }

    /** @return array<string, array{s: array<string,string>, q: string}> */
    public function rows(string $container): array
    {
        return Cache::rememberForever(self::KEY.$container, fn () => $this->build($container));
    }

    /** @return array<string, array{s: array<string,string>, q: string}> rows of every covered container */
    public function allRows(): array
    {
        $rows = [];
        foreach ($this->altlingo->config->containers() as $container) {
            $rows += $this->rows($container);
        }

        return $rows;
    }

    public function build(string $container): array
    {
        $rows = [];
        $handle = AssetContainer::find($container);
        if (! $handle) {
            return [];
        }

        foreach ($handle->assets() as $asset) {
            if ($this->altlingo->isCovered($asset)) {
                $rows[$asset->id()] = $this->row($asset);
            }
        }

        ksort($rows);

        return $rows;
    }

    public function row(Asset $asset): array
    {
        $record = $this->altlingo->record($asset);
        $texts = array_filter(array_merge([$record->mainText($this->altlingo->config->field())], $record->texts[$this->altlingo->config->field()] ?? []));

        return [
            's' => $this->altlingo->statuses($asset, $record),
            'q' => mb_strtolower($asset->path().' '.implode(' ', $texts)),
        ];
    }

    public function update(Asset $asset): void
    {
        $container = $asset->containerHandle();
        if (! in_array($container, $this->altlingo->config->containers(), true)) {
            return;
        }

        $rows = $this->rows($container);
        if ($this->altlingo->isCovered($asset)) {
            $rows[$asset->id()] = $this->row($asset);
            ksort($rows);
        } else {
            unset($rows[$asset->id()]);
        }
        Cache::forever(self::KEY.$container, $rows);
    }

    public function forget(Asset $asset): void
    {
        $container = $asset->containerHandle();
        $rows = $this->rows($container);
        unset($rows[$asset->id()]);
        Cache::forever(self::KEY.$container, $rows);
    }

    public function flush(): void
    {
        foreach (AssetContainer::all() as $container) {
            Cache::forget(self::KEY.$container->handle());
        }
    }

    /**
     * Totals per site: counts of each status and the coverage percent (spec §6).
     *
     * @return array<string, array{total:int, done:int, review:int, fallback:int, missing:int, decorative:int, percent:int}>
     */
    public function totals(?array $rows = null): array
    {
        $rows ??= $this->allRows();
        $out = [];

        foreach ($this->altlingo->config->sites() as $site) {
            $counts = array_fill_keys([Resolver::DONE, Resolver::REVIEW, Resolver::FALLBACK, Resolver::MISSING, Resolver::DECORATIVE], 0);
            foreach ($rows as $row) {
                $status = $row['s'][$site] ?? Resolver::MISSING;
                $counts[$status]++;
            }
            $total = count($rows);
            $out[$site] = $counts + [
                'total' => $total,
                'percent' => $total ? intdiv(($counts[Resolver::DONE] + $counts[Resolver::DECORATIVE]) * 100, $total) : 100,
            ];
        }

        return $out;
    }
}
