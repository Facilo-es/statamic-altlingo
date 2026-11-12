<?php

namespace Facilo\AltLingo\Commands;

use Facilo\AltLingo\Support\Blueprints;
use Facilo\AltLingo\Support\Config;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Site;

class Install extends Command
{
    use RunsInPlease;

    protected $signature = 'altlingo:install {--container=* : Only these containers} {--field= : Field handle, default alt} {--dry-run : Show what would change} {--force : Do not ask}';

    protected $description = 'Turn the alt field of your image containers into one text per language';

    public function handle(Config $config): int
    {
        $field = $this->option('field') ?: $config->field();
        $only = (array) $this->option('container');

        $containers = AssetContainer::all()
            ->filter(fn ($c) => ! $only || in_array($c->handle(), $only, true))
            ->map(fn ($c) => [$c, $c->assets()->filter(fn ($a) => Config::isImage((string) $a->extension()))->count()])
            ->filter(fn ($row) => $row[1] > 0 || $only);

        $todo = $containers->reject(fn ($row) => Blueprints::fieldType($row[0], $field) === 'altlingo');

        if ($todo->isEmpty()) {
            $this->info("Nothing to do: '{$field}' already holds one text per language in every image container.");

            return self::SUCCESS;
        }

        $mainName = Site::get($config->mainSite())?->name() ?? $config->mainSite();
        $names = $todo->map(fn ($row) => "{$row[0]->handle()} ({$row[1]} images)")->implode(', ');
        $question = "Found {$todo->count()} image containers. The field '{$field}' in {$names} will hold one text per language. Existing texts stay as {$mainName}.";

        if ($this->option('dry-run')) {
            $this->line($question);
            $this->line('Dry run: no file was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm($question.' Continue?', true)) {
            return self::FAILURE;
        }

        foreach ($todo as [$container]) {
            Blueprints::setFieldType($container, $field, 'altlingo');
        }

        $this->info('AltLingo is ready. Your templates don\'t need changes: {{ alt }} now shows each language\'s text.');

        return self::SUCCESS;
    }
}
