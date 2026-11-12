<?php

namespace Facilo\AltLingo\Commands;

use Facilo\AltLingo\AltLingo;
use Facilo\AltLingo\Store\AltRecord;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\AssetContainer;

class Import extends Command
{
    use RunsInPlease;

    protected $signature = 'altlingo:import
        {--from= : fields (one field per site, like alt_en) or daun (Multisite Any Field)}
        {--map=* : site:field pairs for odd field names, like en:alt_english}
        {--overwrite : Replace texts that already exist}
        {--dry-run : Count only, change nothing}';

    protected $description = 'Bring alt texts per language in from other fields or addons';

    public function handle(AltLingo $altlingo): int
    {
        $from = $this->option('from');
        if (! in_array($from, ['fields', 'daun'], true)) {
            $this->error('Use --from=fields or --from=daun.');

            return self::FAILURE;
        }

        $field = $altlingo->config->field();
        $main = $altlingo->config->mainSite();
        $others = array_values(array_diff($altlingo->config->sites(), [$main]));
        $map = collect($others)->mapWithKeys(fn ($s) => [$s => "{$field}_{$s}"])->all();
        foreach ((array) $this->option('map') as $pair) {
            [$site, $source] = array_pad(explode(':', $pair, 2), 2, null);
            if ($site && $source && in_array($site, $others, true)) {
                $map[$site] = $source;
            }
        }

        $counts = ['imported' => 0, 'skipped' => 0, 'empty' => 0];
        $dry = (bool) $this->option('dry-run');

        foreach ($altlingo->config->containers() as $handle) {
            foreach (AssetContainer::find($handle)?->assets() ?? [] as $asset) {
                if (! $altlingo->isCovered($asset)) {
                    continue;
                }

                $record = $altlingo->record($asset);
                $raw = $asset->get($field);
                $sources = $from === 'daun'
                    ? (is_array($raw) ? $raw : [])
                    : collect($map)->mapWithKeys(fn ($src, $site) => [$site => $asset->get($src)])->all();

                $changed = false;
                foreach ($others as $site) {
                    $text = AltRecord::clean(is_string($sources[$site] ?? null) ? $sources[$site] : null);
                    if ($text === null) {
                        $counts['empty']++;

                        continue;
                    }
                    if ($record->own($site, $field) !== null && ! $this->option('overwrite')) {
                        $counts['skipped']++;

                        continue;
                    }
                    $record->setOwn($site, $text, $field);
                    $counts['imported']++;
                    $changed = true;
                }

                if ($from === 'daun' && is_array($raw)) {
                    // Multisite Any Field kept every language in alt itself; the main one goes back to a plain string.
                    $record->setMain(is_string($raw[$main] ?? null) ? $raw[$main] : null, $field);
                    $changed = true;
                }

                if ($changed && ! $dry) {
                    $altlingo->save($asset, $record);
                }
            }
        }

        $this->table(['Imported', 'Skipped (text exists)', 'Empty'], [[$counts['imported'], $counts['skipped'], $counts['empty']]]);
        if ($dry) {
            $this->line('Dry run: no file was changed.');
        }

        return self::SUCCESS;
    }
}
