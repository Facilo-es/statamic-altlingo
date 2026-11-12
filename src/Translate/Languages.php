<?php

namespace Facilo\AltLingo\Translate;

use Locale;

class Languages
{
    /** English name of a locale's language, such as `ca_ES` → Catalan. */
    public static function name(string $locale): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayLanguage($locale, 'en') : '';

        return $name !== '' && $name !== $locale ? ucfirst($name) : strtoupper(substr($locale, 0, 2));
    }

    /** DeepL target code by spec §6, or null if DeepL doesn't list the language. */
    public static function deeplTarget(string $locale, array $supported): ?string
    {
        $locale = str_replace('-', '_', $locale);
        $lang = strtolower(substr($locale, 0, 2));

        $code = match (true) {
            strcasecmp($locale, 'en_GB') === 0 => 'EN-GB',
            $lang === 'en' => 'EN-US',
            strcasecmp($locale, 'pt_BR') === 0 => 'PT-BR',
            $lang === 'pt' => 'PT-PT',
            $lang === 'zh' => 'ZH',
            default => strtoupper($lang),
        };

        $supported = array_map('strtoupper', $supported);

        return in_array($code, $supported, true) || in_array(strtok($code, '-'), $supported, true) ? $code : null;
    }

    public static function deeplSource(string $locale): string
    {
        return strtoupper(substr($locale, 0, 2));
    }
}
