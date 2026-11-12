<?php

namespace Facilo\AltLingo\Store;

use Statamic\Contracts\Assets\Asset;

/**
 * Stores texts in the asset's meta file: the main text stays a plain string in the
 * original field, other sites go to `<field>_altlingo`, flags to `altlingo`.
 */
class MetaStore implements AltStore
{
    public const SUFFIX = '_altlingo';

    public const FLAGS = 'altlingo';

    public function read(Asset $asset, array $fields): AltRecord
    {
        $record = new AltRecord;

        foreach ($fields as $field) {
            $main = $asset->get($field);
            $record->main[$field] = AltRecord::clean(is_array($main) ? ($main['main'] ?? null) : $main);

            foreach ((array) $asset->get($field.self::SUFFIX) as $site => $text) {
                if (($text = AltRecord::clean($text)) !== null) {
                    $record->texts[$field][(string) $site] = $text;
                }
            }
        }

        $flags = (array) $asset->get(self::FLAGS);
        $record->decorative = (bool) ($flags['decorative'] ?? false);
        $record->review = array_values(array_map('strval', (array) ($flags['review'] ?? [])));
        $record->machine = array_values(array_map('strval', (array) ($flags['machine'] ?? [])));

        return $record;
    }

    public function write(Asset $asset, AltRecord $record, array $fields): void
    {
        $this->apply($asset, $record, $fields);
        $asset->save();
    }

    public function apply(Asset $asset, AltRecord $record, array $fields): void
    {
        foreach ($fields as $field) {
            $main = $record->mainText($field);
            $main === null ? $asset->remove($field) : $asset->set($field, $main);

            $texts = $record->texts[$field] ?? [];
            ksort($texts);
            $texts ? $asset->set($field.self::SUFFIX, $texts) : $asset->remove($field.self::SUFFIX);
        }

        $flags = array_filter([
            'decorative' => $record->decorative ?: null,
            'review' => $record->review ?: null,
            'machine' => $record->machine ?: null,
        ]);

        $flags ? $asset->set(self::FLAGS, $flags) : $asset->remove(self::FLAGS);
    }
}
