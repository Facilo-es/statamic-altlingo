<?php

namespace Facilo\AltLingo\Store;

use Statamic\Contracts\Assets\Asset;

/**
 * An in-memory store, used to prove the whole suite runs through AltStore alone (T28).
 */
class MemoryStore implements AltStore
{
    /** @var array<string, AltRecord> */
    private array $records = [];

    public function read(Asset $asset, array $fields): AltRecord
    {
        $record = clone ($this->records[$asset->id()] ?? new AltRecord);

        foreach ($fields as $field) {
            $main = $asset->get($field);
            $record->main[$field] = AltRecord::clean(is_array($main) ? ($main['main'] ?? null) : $main);
        }

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
        }

        $this->records[$asset->id()] = clone $record;
    }
}
