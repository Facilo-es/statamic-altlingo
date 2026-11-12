<?php

namespace Facilo\AltLingo\Tests\Feature;

use PHPUnit\Framework\TestCase;

class LangTest extends TestCase
{
    /** T27: every language has the same keys as English. */
    public function test_t27_every_language_has_every_key(): void
    {
        $root = dirname(__DIR__, 2).'/lang';
        $languages = array_map('basename', glob($root.'/*', GLOB_ONLYDIR));
        $this->assertEqualsCanonicalizing(['de', 'en', 'es', 'fr', 'it', 'nl', 'pt'], $languages);

        foreach (glob($root.'/en/*.php') as $file) {
            $en = array_keys(require $file);
            foreach ($languages as $lang) {
                $path = "{$root}/{$lang}/".basename($file);
                $this->assertFileExists($path);
                $keys = array_keys(require $path);
                $this->assertSame([], array_values(array_diff($en, $keys)), "{$lang}/".basename($file).' is missing keys');
                $this->assertSame([], array_values(array_diff($keys, $en)), "{$lang}/".basename($file).' has extra keys');
            }
        }
    }
}
