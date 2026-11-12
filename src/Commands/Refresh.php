<?php

namespace Facilo\AltLingo\Commands;

use Facilo\AltLingo\Coverage\CoverageCache;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class Refresh extends Command
{
    use RunsInPlease;

    protected $signature = 'altlingo:refresh';

    protected $description = 'Rebuild the alt text coverage numbers';

    public function handle(CoverageCache $cache): int
    {
        $cache->flush();
        $rows = $cache->allRows();
        $this->info('Coverage rebuilt for '.count($rows).' images.');

        return self::SUCCESS;
    }
}
