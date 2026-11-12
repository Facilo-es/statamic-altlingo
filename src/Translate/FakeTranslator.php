<?php

namespace Facilo\AltLingo\Translate;

/** For tests and local demos: returns "[en] text" without calling anyone. */
class FakeTranslator implements Translator
{
    public array $calls = [];

    public function __construct(private array $unsupported = [])
    {
    }

    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        $this->calls[] = [$text, $sourceLocale, $targetLocale];
        if (in_array(substr($targetLocale, 0, 2), $this->unsupported, true)) {
            throw TranslationFailed::unsupported($targetLocale);
        }

        return '['.substr($targetLocale, 0, 2).'] '.$text;
    }
}
