<?php

namespace Facilo\AltLingo\Tests;

use Facilo\AltLingo\Support\Config;

/**
 * Config with settings and edition set by the test, instead of the addon's settings file.
 */
class TestConfig extends Config
{
    public array $settings = [];

    public bool $pro = true;

    public function isPro(): bool
    {
        return $this->pro;
    }

    public function setting(string $key, $default = null)
    {
        $value = $this->settings[$key] ?? null;

        return ($value === null || $value === '' || $value === []) ? $default : $value;
    }
}
