<?php

namespace Facilo\AltLingo\Http\Controllers;

use Facilo\AltLingo\Coverage\CoverageCache;
use Facilo\AltLingo\Resolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class GridController extends CpController
{
    use Concerns;

    public const PER_PAGE = 50;

    public function index()
    {
        $this->proOnly();
        $this->authorize('view altlingo');
        $config = $this->altlingo()->config;
        $user = User::current();

        $containers = collect($config->containers())->map(fn ($h) => AssetContainer::find($h))->filter();
        $folders = collect(app(CoverageCache::class)->allRows())->keys()
            ->map(fn ($id) => [explode('::', $id, 2)[0], dirname(explode('::', $id, 2)[1])])
            ->reject(fn ($f) => $f[1] === '.')
            ->unique(fn ($f) => $f[0].'::'.$f[1])
            ->map(fn ($f) => ['container' => $f[0], 'path' => $f[1]])
            ->sortBy('path')->values();

        return Inertia::render('altlingo::AltText', [
            'sites' => $this->sitesProp(),
            'totals' => app(CoverageCache::class)->totals(),
            'containers' => $containers->map(fn ($c) => ['value' => $c->handle(), 'label' => $c->title()])->values(),
            'folders' => $folders,
            'multisite' => $config->isMultisite(),
            'can' => [
                'edit' => $user->can('edit altlingo'),
                'translate' => $user->can('translate altlingo'),
            ],
            'translator' => $config->translatorConfigured(),
            'queue' => config('queue.default') !== 'sync',
            'docsUrl' => 'https://github.com/Facilo-es/statamic-altlingo#translation-keys',
            'urls' => [
                'rows' => cp_route('altlingo.rows'),
                'cell' => cp_route('altlingo.cell'),
                'bulk' => cp_route('altlingo.bulk'),
                'translate' => cp_route('altlingo.translate'),
                'batch' => cp_route('altlingo.translate-batch'),
                'progress' => cp_route('altlingo.translate-progress', ['run' => '__run__']),
                'export' => cp_route('altlingo.export'),
                'preview' => cp_route('altlingo.import-preview'),
                'apply' => cp_route('altlingo.import-apply'),
            ],
            'strings' => __('altlingo::messages'),
            'fieldStrings' => __('altlingo::fieldtype'),
        ]);
    }

    public function rows(Request $request)
    {
        $this->proOnly();
        $this->authorize('view altlingo');

        $ids = $this->filteredIds($request);
        $total = count($ids);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) $request->input('page', 1)), $pages);
        $slice = array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return [
            'data' => collect($slice)->map(fn ($id) => Asset::find($id))->filter()->map(fn ($a) => $this->row($a))->values(),
            'meta' => ['total' => $total, 'page' => $page, 'pages' => $pages, 'ids' => $request->boolean('with_ids') ? $ids : null],
        ];
    }

    public function cell(Request $request)
    {
        $this->proOnly();
        $this->authorize('edit altlingo');
        $data = $request->validate(['id' => 'required|string', 'site' => 'required|string', 'text' => 'nullable|string|max:1000']);
        $this->authorizeSite($data['site']);
        $asset = $this->asset($data['id']);

        $this->altlingo()->edit($asset, $data['site'], $data['text'] ?? null);

        return ['row' => $this->row(Asset::find($asset->id())), 'totals' => app(CoverageCache::class)->totals()];
    }

    public function bulk(Request $request)
    {
        $this->proOnly();
        $this->authorize('edit altlingo');
        $data = $request->validate([
            'action' => 'required|in:decorative,reviewed,clear',
            'ids' => 'required|array|max:10000',
            'ids.*' => 'string',
            'site' => 'nullable|string',
        ]);
        $altlingo = $this->altlingo();
        $sites = $data['action'] === 'decorative' ? [$altlingo->config->mainSite()] : [$data['site'] ?? ''];
        foreach ($sites as $site) {
            $this->authorizeSite($site);
        }

        $changed = 0;
        foreach ($data['ids'] as $id) {
            $asset = $this->asset($id);
            $record = $altlingo->record($asset);
            switch ($data['action']) {
                case 'decorative':
                    if ($record->decorative) {
                        continue 2;
                    }
                    $record->decorative = true;
                    $altlingo->save($asset, $record);
                    break;
                case 'reviewed':
                    if (! $record->isReview($sites[0])) {
                        continue 2;
                    }
                    $altlingo->save($asset, $record->markReview($sites[0], false));
                    break;
                case 'clear':
                    if (! $altlingo->edit($asset, $sites[0], null)) {
                        continue 2;
                    }
                    break;
            }
            $changed++;
        }

        return ['changed' => $changed, 'totals' => app(CoverageCache::class)->totals()];
    }

    private function sitesProp(): array
    {
        $config = $this->altlingo()->config;

        return collect($config->sites())->map(function ($handle) use ($config) {
            $site = Site::get($handle);
            $chain = $config->chains()[$handle] ?? [];
            $fallback = $chain[0] ?? $config->mainSite();
            $empty = ($config->whenMissing()[$handle] ?? 'main') === 'empty' && ! $chain;

            return [
                'handle' => $handle,
                'name' => $site->name(),
                'lang' => $site->lang(),
                'main' => $handle === $config->mainSite(),
                'editable' => $this->canSite($handle),
                'fallbackName' => $empty ? null : Site::get($fallback)?->name(),
            ];
        })->values()->all();
    }

    private function row($asset): array
    {
        $altlingo = $this->altlingo();
        $field = $altlingo->config->field();
        $record = $altlingo->record($asset);
        $main = $altlingo->config->mainSite();
        $resolver = $altlingo->config->resolver();

        return [
            'id' => $asset->id(),
            'file' => $asset->basename(),
            'folder' => trim($asset->containerHandle().'/'.(in_array($asset->folder(), ['/', '.'], true) ? '' : $asset->folder()), '/'),
            'thumbnail' => $asset->thumbnailUrl('small'),
            'edit_url' => $asset->editUrl(),
            'decorative' => $record->decorative,
            'texts' => collect($altlingo->config->sites())->mapWithKeys(fn ($s) => [$s => $s === $main ? $record->mainText($field) : $record->own($s, $field)])->all(),
            'hints' => collect($altlingo->config->sites())->mapWithKeys(fn ($s) => [$s => $resolver->resolve($record, $s, $field)])->all(),
            'status' => $altlingo->statuses($asset, $record),
        ];
    }
}
