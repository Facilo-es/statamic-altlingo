<?php

namespace Facilo\AltLingo\Support;

use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Site;

/**
 * Reads AltLingo's settings and edition, and Statamic's sites, in one place.
 */
class Config
{
    public const PACKAGE = 'facilo/statamic-altlingo';

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    public function addon()
    {
        return Addon::get(self::PACKAGE);
    }

    public function isPro(): bool
    {
        return $this->addon()?->edition() === 'pro';
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

    /**
     * The alt field first, then the extra Pro fields (F10).
     */
    public function fields(): array
    {
        $extra = $this->isPro() ? array_values((array) $this->setting('extra_fields', [])) : [];

        return array_values(array_unique(array_merge([$this->field()], array_diff($extra, [$this->field()]))));
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

    /** @return array<string, array<int, string>> */
    public function chains(): array
    {
        if (! $this->isPro()) {
            return [];
        }

        $chains = [];
        foreach ($this->sites() as $site) {
            $chain = array_values(array_filter((array) $this->setting("fallback_{$site}", []), fn ($s) => $s !== $site && in_array($s, $this->sites(), true)));
            if ($chain) {
                $chains[$site] = $chain;
            }
        }

        return $chains;
    }

    /** @return array<string, string> 'main' or 'empty' per site */
    public function whenMissing(): array
    {
        $out = [];
        foreach ($this->sites() as $site) {
            $out[$site] = $this->isPro() && $this->setting("missing_{$site}") === 'empty' ? 'empty' : 'main';
        }

        return $out;
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
        return (new \Facilo\AltLingo\Resolver($this->mainSite(), $this->sites(), $this->chains(), $this->whenMissing()))->withAltField($this->field());
    }

    public function provider(): string
    {
        return $this->setting('provider', 'deepl') === 'openai' ? 'openai' : 'deepl';
    }

    /** The key for the chosen provider, read from .env only. */
    public function apiKey(): ?string
    {
        $key = $this->provider() === 'openai' ? env('ALTLINGO_OPENAI_KEY') : env('ALTLINGO_DEEPL_KEY');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public function translatorConfigured(): bool
    {
        return app()->bound('altlingo.translator.fake') || $this->apiKey() !== null;
    }

    public static function isImage(string $extension): bool
    {
        return in_array(strtolower($extension), self::IMAGE_EXTENSIONS, true);
    }
}
