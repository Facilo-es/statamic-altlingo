<?php

namespace Facilo\AltLingo\Http\Controllers;

use Facilo\AltLingo\Coverage\CoverageCache;
use Facilo\AltLingo\Translate\Manager;
use Facilo\AltLingo\Translate\TranslateBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Facades\Asset;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Bulk translation runs in batches of 25: on the queue if one is set up, otherwise the page
 * calls `batch` again and again and shows the progress bar.
 */
class TranslateController extends CpController
{
    use Concerns;

    public const BATCH = 25;

    public function start(Request $request)
    {
        $this->proOnly();
        $this->authorize('translate altlingo');
        $data = $request->validate([
            'ids' => 'required|array|max:10000',
            'ids.*' => 'string',
            'sites' => 'required|array|min:1',
            'sites.*' => 'string',
            'overwrite' => 'boolean',
        ]);
        $main = $this->altlingo()->config->mainSite();
        $sites = array_values(array_diff(array_unique($data['sites']), [$main]));
        abort_if($sites === [], 422);
        foreach ($sites as $site) {
            $this->authorizeSite($site);
        }

        $jobs = [];
        foreach ($data['ids'] as $id) {
            foreach ($sites as $site) {
                $jobs[] = [$id, $site];
            }
        }

        $run = (string) Str::uuid();
        $state = ['user' => auth()->id(), 'jobs' => $jobs, 'next' => 0, 'ok' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => [], 'overwrite' => (bool) ($data['overwrite'] ?? false)];
        Cache::put($this->key($run), $state, 86400);

        if (config('queue.default') !== 'sync') {
            // One chain, so batches run one after another and never overwrite each other's counts.
            Bus::chain(array_map(fn ($from) => new TranslateBatch($run, $from, self::BATCH), range(0, count($jobs) - 1, self::BATCH)))->dispatch();
        }

        return ['run' => $run, 'total' => count($jobs), 'queued' => config('queue.default') !== 'sync'];
    }

    public function batch(Request $request)
    {
        $this->proOnly();
        $this->authorize('translate altlingo');
        $run = (string) $request->input('run');
        $state = $this->state($run);

        $state = self::process($run, $state['next'], self::BATCH);

        return $this->summary($state);
    }

    public function progress(string $run)
    {
        $this->proOnly();
        $this->authorize('translate altlingo');

        return $this->summary($this->state($run));
    }

    /** Translate one empty tab of one image from the image editor. */
    public function one(Request $request, string $image)
    {
        $this->proOnly();
        $this->authorize('translate altlingo');
        $site = (string) $request->input('site');
        $this->authorizeSite($site);
        // The route parameter isn't named "asset": Statamic binds that name to its own asset routes.
        $asset = $this->asset(base64_decode($image, true) ?: $image);

        $result = app(Manager::class)->translate($asset, $site, true);
        abort_if($result['status'] !== 'ok', 422, $result['message'] ?? '');

        return ['text' => $result['text']];
    }

    /** Runs jobs [$from, $from + $count) of a run and stores the new state. Used by the page and the queue. */
    public static function process(string $run, int $from, int $count): array
    {
        $key = 'altlingo.translate.'.$run;
        $state = Cache::get($key);
        if (! $state) {
            return [];
        }

        $manager = app(Manager::class);
        $end = min($from + $count, count($state['jobs']));
        for ($i = $from; $i < $end; $i++) {
            [$id, $site] = $state['jobs'][$i];
            $asset = Asset::find($id);
            $result = $asset ? $manager->translate($asset, $site, $state['overwrite']) : ['status' => 'failed', 'message' => 'Image not found'];
            $state[$result['status']]++;
            if (isset($result['message']) && count($state['errors']) < 50) {
                $state['errors'][] = ['id' => $id, 'site' => $site, 'message' => $result['message']];
            }
        }
        $state['next'] = max($state['next'], $end);
        Cache::put($key, $state, 86400);

        return $state;
    }

    private function key(string $run): string
    {
        return 'altlingo.translate.'.$run;
    }

    private function state(string $run): array
    {
        $state = Cache::get($this->key($run));
        abort_unless($state && $state['user'] === auth()->id(), 404);

        return $state;
    }

    private function summary(array $state): array
    {
        $done = $state['ok'] + $state['failed'] + $state['skipped'];

        return [
            'total' => count($state['jobs']),
            'done' => $done,
            'ok' => $state['ok'],
            'failed' => $state['failed'],
            'skipped' => $state['skipped'],
            'errors' => $state['errors'],
            'finished' => $done >= count($state['jobs']),
            'totals' => $done >= count($state['jobs']) ? app(CoverageCache::class)->totals() : null,
        ];
    }
}
