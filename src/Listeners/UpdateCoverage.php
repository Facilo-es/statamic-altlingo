<?php

namespace Facilo\AltLingo\Listeners;

use Facilo\AltLingo\Coverage\CoverageCache;
use Statamic\Events\AssetDeleted;
use Statamic\Events\AssetSaved;

class UpdateCoverage
{
    public function __construct(private CoverageCache $cache)
    {
    }

    public function saved(AssetSaved $event): void
    {
        $this->cache->update($event->asset);
    }

    public function deleted(AssetDeleted $event): void
    {
        $this->cache->forget($event->asset);
    }
}
