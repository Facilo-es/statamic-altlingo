<?php

namespace Facilo\AltLingo\Commands;

use Facilo\AltLingo\Store\MetaStore;
use Facilo\AltLingo\Support\Blueprints;
use Facilo\AltLingo\Support\Config;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\AssetContainer;

class Uninstall extends Command
{
    use RunsInPlease;

    protected $signature = 'altlingo:uninstall {--purge : Also delete the other languages\' texts} {--force : Do not ask}';

    protected $description = 'Turn AltLingo fields back into plain text fields';

    public function handle(Config $config): int
    {
        $fields = $config->fields();

        if (! $this->option('force') && ! $this->confirm('The fields '.implode(', ', $fields).' go back to plain text. '.($this->option('purge') ? 'Texts for other languages will be deleted.' : 'Texts for other languages are kept in the meta files.').' Continue?', true)) {
            return self::FAILURE;
        }

        $changed = 0;
        $purged = 0;
        foreach (AssetContainer::all() as $container) {
            foreach ($fields as $field) {
                if (Blueprints::fieldType($container, $field) === 'altlingo') {
                    Blueprints::setFieldType($container, $field, 'text');
                    $changed++;
                }
            }

            if (! $this->option('purge')) {
                continue;
            }

            foreach ($container->assets() as $asset) {
                $dirty = false;
                foreach (array_merge(array_map(fn ($f) => $f.MetaStore::SUFFIX, $fields), [MetaStore::FLAGS]) as $key) {
                    // has() does not load the meta file, get() does.
                    if ($asset->get($key) !== null) {
                        $asset->remove($key);
                        $dirty = true;
                    }
                }
                if ($dirty) {
                    $asset->saveQuietly();
                    $purged++;
                }
            }
        }

        $this->info("Done. {$changed} fields are plain text again.".($this->option('purge') ? " Other languages removed from {$purged} images." : ''));

        return self::SUCCESS;
    }
}
