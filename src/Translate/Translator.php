<?php

namespace Facilo\AltLingo\Translate;

interface Translator
{
    /**
     * Translate one alt text. Locales are Statamic site locales such as `es_ES` or `en_US`.
     *
     * @throws TranslationFailed when the provider can't or won't translate it
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale): string;
}
