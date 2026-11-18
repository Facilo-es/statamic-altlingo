<?php

namespace Facilo\AltLingo\Support;

use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Site;

/**
 * Reads AltLingo's settings and Statamic's sites in one place.
 */
class Config
{
    public const PACKAGE = 'facilo/statamic-altlingo';

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    public function addon()
    {
        return Addon::get(self::PACKAGE);
    }

    /** Statamic builds the settings blueprint while loading settings, and that blueprint reads this class. */
    private bool $loading = false;

    public function setting(string $key, $default = null)
    {
        if ($this->loading) {
            return $default;
        }

        $this->loading = true;
        try {
            $value = $this->addon()?->setting($key);
        } finally {
            $this->loading = false;
        }

        return ($value === null || $value === '' || $value === []) ? $default : $value;
    }

    public function field(): string
    {
        return (string) $this->setting('field', 'alt');
    }

    /** The fields AltLingo stores per language. */
    public function fields(): array
    {
        return [$this->field()];
    }

    public function mainSite(): string
    {
        return Site::default()->handle();
    }

    /** @return array<int, string> */
    public function sites(): array
    {
        return Site::all()->keys()->map(fn ($h) => (string) $h)->values()->all();
    }

    public function isMultisite(): bool
    {
        return count($this->sites()) > 1;
    }

    /** @return array<int, string> handles of covered containers */
    public function containers(): array
    {
        $all = AssetContainer::all()->map->handle()->values()->all();
        $chosen = array_values(array_intersect((array) $this->setting('containers', []), $all));

        return $chosen ?: $all;
    }

    public function resolver(): \Facilo\AltLingo\Resolver
    {
        return (new \Facilo\AltLingo\Resolver($this->mainSite(), $this->sites()))->withAltField($this->field());
    }

    public static function isImage(string $extension): bool
    {
        return in_array(strtolower($extension), self::IMAGE_EXTENSIONS, true);
    }
}
