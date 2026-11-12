<?php

namespace Facilo\AltLingo\Http\Controllers;

use Facilo\AltLingo\Coverage\CoverageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Facades\Asset;
use Statamic\Http\Controllers\CP\CpController;

class CsvController extends CpController
{
    use Concerns;

    public const MAX_BYTES = 5 * 1024 * 1024;

    public function export(Request $request)
    {
        $this->proOnly();
        $this->authorize('view altlingo');
        $altlingo = $this->altlingo();
        $sites = $altlingo->config->sites();
        $main = $altlingo->config->mainSite();
        $field = $altlingo->config->field();
        $ids = $this->filteredIds($request);

        return response()->streamDownload(function () use ($ids, $sites, $main, $field, $altlingo) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['id', 'container', 'path', 'image_url'], $sites, ['decorative']), escape: '');
            foreach ($ids as $id) {
                if (! $asset = Asset::find($id)) {
                    continue;
                }
                $record = $altlingo->record($asset);
                $texts = array_map(fn ($s) => $s === $main ? $record->mainText($field) : $record->own($s, $field), $sites);
                $row = array_merge([$asset->id(), $asset->containerHandle(), $asset->path(), $asset->absoluteUrl()], $texts, [$record->decorative ? 'yes' : '']);
                fputcsv($out, array_map([self::class, 'safe'], $row), escape: '');
            }
            fclose($out);
        }, 'alt-texts-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Spreadsheet apps run cells that start with these as formulas, so they get a leading quote. */
    public static function safe($value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    public function preview(Request $request)
    {
        $this->proOnly();
        $this->authorize('edit altlingo');
        $file = $request->file('file');
        abort_unless($file && $file->isValid(), 422, __('altlingo::messages.not_csv'));
        abort_if($file->getSize() > self::MAX_BYTES, 422, __('altlingo::messages.too_big'));

        $parsed = $this->parse(file_get_contents($file->getRealPath()));
        $token = (string) Str::uuid();
        Cache::put('altlingo.import.'.$token, ['user' => auth()->id(), 'changes' => $parsed['changes']], 3600);

        return [
            'token' => $token,
            'changed' => collect($parsed['changes'])->where('before', '!==', null)->count(),
            'new' => collect($parsed['changes'])->where('before', null)->count(),
            'unmatched' => $parsed['unmatched'],
            'errors' => $parsed['errors'],
            'changes' => array_slice($parsed['changes'], 0, 200),
        ];
    }

    public function apply(Request $request)
    {
        $this->proOnly();
        $this->authorize('edit altlingo');
        $import = Cache::pull('altlingo.import.'.$request->input('token'));
        abort_unless($import && $import['user'] === auth()->id(), 404);
        $review = $request->boolean('review');

        $written = 0;
        foreach ($import['changes'] as $change) {
            if (! $this->canSite($change['site']) || ! ($asset = Asset::find($change['id']))) {
                continue;
            }
            $written += (int) $this->altlingo()->edit($asset, $change['site'], $change['after'], $review);
        }

        return ['written' => $written, 'totals' => app(CoverageCache::class)->totals()];
    }

    /**
     * Reads the CSV as data only. Empty cells never erase a text, and only sites the user can edit count.
     *
     * @return array{changes: array<int, array>, unmatched: int, errors: array<int, string>}
     */
    public function parse(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $altlingo = $this->altlingo();
        $sites = $altlingo->config->sites();
        $main = $altlingo->config->mainSite();
        $field = $altlingo->config->field();

        $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), $this->records($csv));
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?? []);
        abort_unless(in_array('id', $header, true), 422, __('altlingo::messages.not_csv'));
        $columns = array_intersect($header, $sites);

        $changes = [];
        $errors = [];
        $unmatched = 0;
        foreach ($rows as $n => $cells) {
            $line = $n + 2;
            if ($cells === [null] || $cells === ['']) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $h) {
                $row[$h] = $cells[$i] ?? '';
            }
            $asset = $row['id'] !== '' ? Asset::find($row['id']) : null;
            if (! $asset || ! $altlingo->isCovered($asset)) {
                $unmatched++;
                $errors[] = __('altlingo::messages.row_error', ['line' => $line, 'reason' => __('altlingo::messages.unknown_id')]);

                continue;
            }
            $record = $altlingo->record($asset);
            foreach ($columns as $site) {
                $after = trim(self::unsafe((string) $row[$site]));
                if ($after === '' || ! $this->canSite($site)) {
                    continue;
                }
                $before = $site === $main ? $record->mainText($field) : $record->own($site, $field);
                if ($after !== $before) {
                    $changes[] = ['id' => $asset->id(), 'file' => $asset->basename(), 'thumbnail' => $asset->thumbnailUrl('small'), 'site' => $site, 'line' => $line, 'before' => $before, 'after' => $after];
                }
            }
        }

        return ['changes' => $changes, 'unmatched' => $unmatched, 'errors' => $errors];
    }

    /** Undo the export's leading quote. */
    private static function unsafe(string $value): string
    {
        return strlen($value) > 1 && $value[0] === "'" && in_array($value[1], ['=', '+', '-', '@'], true) ? substr($value, 1) : $value;
    }

    /** Splits CSV text into records, keeping line breaks inside quoted cells. */
    private function records(string $csv): array
    {
        $records = [];
        $current = '';
        $quotes = 0;
        foreach (preg_split('/(\r\n|\n|\r)/', $csv) as $line) {
            $current = $current === '' && $quotes === 0 ? $line : $current."\n".$line;
            $quotes += substr_count($line, '"');
            if ($quotes % 2 === 0) {
                $records[] = $current;
                $current = '';
                $quotes = 0;
            }
        }

        return array_values(array_filter($records, fn ($r) => trim($r) !== ''));
    }
}
