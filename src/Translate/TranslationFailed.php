<?php

namespace Facilo\AltLingo\Translate;

use RuntimeException;

class TranslationFailed extends RuntimeException
{
    /** True when the language can't be translated at all, so the row is skipped rather than failed. */
    public bool $unsupported = false;

    public static function unsupported(string $locale): self
    {
        $e = new self(__('altlingo::messages.unsupported', ['language' => Languages::name($locale)]));
        $e->unsupported = true;

        return $e;
    }
}
