<?php

namespace Facilo\AltLingo;

use Facilo\AltLingo\Store\AltRecord;

/**
 * The resolution and status rules from the spec, section 6. Pure: no Statamic calls.
 */
class Resolver
{
    public const DONE = 'done';

    public const REVIEW = 'review';

    public const FALLBACK = 'fallback';

    public const MISSING = 'missing';

    public const DECORATIVE = 'decorative';

    /**
     * @param  array<int, string>  $sites
     * @param  array<string, array<int, string>>  $chains  Fallback chain per site (Pro).
     * @param  array<string, string>  $whenMissing  'main' or 'empty' per site (Pro; Free is always 'main').
     */
    public function __construct(
        public readonly string $main,
        public readonly array $sites,
        public readonly array $chains = [],
        public readonly array $whenMissing = [],
    ) {
    }

    public function resolve(AltRecord $record, string $site, string $field = 'alt'): string
    {
        if ($record->decorative && $this->isAlt($field)) {
            return '';
        }

        return $this->find($record, $site, $field) ?? '';
    }

    /**
     * The text the site would show, or null when it shows nothing.
     */
    private function find(AltRecord $record, string $site, string $field): ?string
    {
        if (($text = $this->direct($record, $site, $field)) !== null) {
            return $text;
        }

        foreach ($this->chains[$site] ?? [] as $next) {
            if (($text = $this->direct($record, $next, $field)) !== null) {
                return $text;
            }
        }

        if (($this->whenMissing[$site] ?? 'main') === 'empty') {
            return null;
        }

        return $record->mainText($field);
    }

    private function direct(AltRecord $record, string $site, string $field): ?string
    {
        return $site === $this->main ? $record->mainText($field) : $record->own($site, $field);
    }

    public function status(AltRecord $record, string $site, string $field = 'alt'): string
    {
        if ($record->decorative && $this->isAlt($field)) {
            return self::DECORATIVE;
        }

        if ($site === $this->main) {
            return $record->mainText($field) !== null ? self::DONE : self::MISSING;
        }

        if ($record->own($site, $field) !== null) {
            return $record->isReview($site) && $this->isAlt($field) ? self::REVIEW : self::DONE;
        }

        return $this->find($record, $site, $field) !== null ? self::FALLBACK : self::MISSING;
    }

    /**
     * Which site a fallback text comes from, for the "Uses {language}" state.
     */
    public function source(AltRecord $record, string $site, string $field = 'alt'): ?string
    {
        if ($this->direct($record, $site, $field) !== null) {
            return $site;
        }

        foreach ($this->chains[$site] ?? [] as $next) {
            if ($this->direct($record, $next, $field) !== null) {
                return $next;
            }
        }

        return ($this->whenMissing[$site] ?? 'main') !== 'empty' && $record->mainText($field) !== null ? $this->main : null;
    }

    private function isAlt(string $field): bool
    {
        return $field === ($this->altField ?? 'alt');
    }

    public ?string $altField = null;

    public function withAltField(string $field): self
    {
        $this->altField = $field;

        return $this;
    }
}
