<?php

namespace Facilo\AltLingo;

use Facilo\AltLingo\Store\AltRecord;
use Facilo\AltLingo\Store\AltStore;
use Facilo\AltLingo\Support\Config;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Site;

/**
 * The one place the rest of the addon asks for texts, statuses and resolved values.
 */
class AltLingo
{
    private ?string $siteOverride = null;

    public function __construct(public readonly AltStore $store, public readonly Config $config)
    {
    }

    public function record(Asset $asset): AltRecord
    {
        return $this->store->read($asset, $this->config->fields());
    }

    public function save(Asset $asset, AltRecord $record): void
    {
        $this->store->write($asset, $record, $this->config->fields());
    }

    public function currentSite(): string
    {
        return $this->siteOverride ?? Site::current()->handle();
    }

    public function withSite(?string $site, callable $callback)
    {
        $previous = $this->siteOverride;
        $this->siteOverride = $site;

        try {
            return $callback();
        } finally {
            $this->siteOverride = $previous;
        }
    }

    public function setSiteOverride(?string $site): void
    {
        $this->siteOverride = $site;
    }

    public function resolve(Asset $asset, ?string $site = null, ?string $field = null): string
    {
        return $this->config->resolver()->resolve($this->record($asset), $site ?? $this->currentSite(), $field ?? $this->config->field());
    }

    /** @return array<string, string> status per site */
    public function statuses(Asset $asset, ?AltRecord $record = null): array
    {
        $record ??= $this->record($asset);
        $resolver = $this->config->resolver();

        return collect($this->config->sites())->mapWithKeys(fn ($site) => [$site => $resolver->status($record, $site, $this->config->field())])->all();
    }

    public function isCovered(Asset $asset): bool
    {
        return Config::isImage((string) $asset->extension())
            && in_array($asset->containerHandle(), $this->config->containers(), true);
    }

    /**
     * Whether a field handle on this asset's blueprint uses the AltLingo fieldtype.
     */
    public function usesField(Asset $asset, string $field): bool
    {
        return $asset->blueprint()?->field($field)?->type() === 'altlingo';
    }

    /**
     * A person writes one site's alt text (grid, CSV). Their text is no longer To review, and a new
     * main text sends machine translations back to To review. Returns false if nothing changed.
     */
    public function edit(Asset $asset, string $site, ?string $text, bool $review = false): bool
    {
        $field = $this->config->field();
        $record = $this->record($asset);
        $main = $site === $this->config->mainSite();
        $before = $main ? $record->mainText($field) : $record->own($site, $field);

        if (AltRecord::clean($text) === $before) {
            return false;
        }

        if ($main) {
            $record->setMain($text, $field);
            foreach ($record->machine as $other) {
                if ($record->own($other, $field) !== null) {
                    $record->markReview($other);
                }
            }
        } else {
            $record->setOwn($site, $text, $field)->markReview($site, $review && AltRecord::clean($text) !== null)->markMachine($site, false);
        }

        $this->save($asset, $record);

        return true;
    }
}
