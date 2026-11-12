<?php

namespace Facilo\AltLingo\Store;

/**
 * Every alt-text fact AltLingo keeps about one image, independent of where it is stored.
 */
class AltRecord
{
    /**
     * @param  array<string, string|null>  $main  Main-site text per field handle.
     * @param  array<string, array<string, string>>  $texts  Other sites' texts per field handle, then site handle.
     * @param  array<int, string>  $review  Sites whose alt text was machine-translated and not yet checked.
     * @param  array<int, string>  $machine  Sites whose alt text was last written by a translation provider.
     */
    public function __construct(
        public array $main = [],
        public array $texts = [],
        public bool $decorative = false,
        public array $review = [],
        public array $machine = [],
    ) {
    }

    public static function clean($text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    public function mainText(string $field = 'alt'): ?string
    {
        return self::clean($this->main[$field] ?? null);
    }

    public function own(string $site, string $field = 'alt'): ?string
    {
        return self::clean($this->texts[$field][$site] ?? null);
    }

    public function setMain(?string $text, string $field = 'alt'): self
    {
        $this->main[$field] = self::clean($text);

        return $this;
    }

    public function setOwn(string $site, ?string $text, string $field = 'alt'): self
    {
        $text = self::clean($text);

        if ($text === null) {
            unset($this->texts[$field][$site]);
        } else {
            $this->texts[$field][$site] = $text;
        }

        if (($this->texts[$field] ?? null) === []) {
            unset($this->texts[$field]);
        }

        return $this;
    }

    public function isReview(string $site): bool
    {
        return in_array($site, $this->review, true);
    }

    public function markReview(string $site, bool $on = true): self
    {
        $this->review = $this->toggle($this->review, $site, $on);

        return $this;
    }

    public function markMachine(string $site, bool $on = true): self
    {
        $this->machine = $this->toggle($this->machine, $site, $on);

        return $this;
    }

    public function isMachine(string $site): bool
    {
        return in_array($site, $this->machine, true);
    }

    private function toggle(array $list, string $site, bool $on): array
    {
        $list = array_values(array_diff($list, [$site]));

        if ($on) {
            $list[] = $site;
            sort($list);
        }

        return $list;
    }
}
