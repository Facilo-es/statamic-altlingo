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
     */
    public function __construct(
        public readonly string $main,
        public readonly array $sites,
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
        return $this->direct($record, $site, $field) ?? $record->mainText($field);
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

        return $record->mainText($field) !== null ? $this->main : null;
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
