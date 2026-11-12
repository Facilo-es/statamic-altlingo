<?php

namespace Facilo\AltLingo\Translate;

use Facilo\AltLingo\Http\Controllers\TranslateController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class TranslateBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public string $run, public int $from, public int $count)
    {
    }

    public function handle(): void
    {
        TranslateController::process($this->run, $this->from, $this->count);
    }
}
