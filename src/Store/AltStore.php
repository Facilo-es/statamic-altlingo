<?php

namespace Facilo\AltLingo\Store;

use Statamic\Contracts\Assets\Asset;

/**
 * Every read and write of per-language texts goes through this interface (F11),
 * so a later CoreStore can take over when Statamic stores localized asset meta itself.
 */
interface AltStore
{
    /**
     * @param  array<int, string>  $fields  Field handles to read, main alt field first.
     */
    public function read(Asset $asset, array $fields): AltRecord;

    /**
     * Writes the record and saves the asset.
     */
    public function write(Asset $asset, AltRecord $record, array $fields): void;

    /**
     * Applies the record to the asset's data without saving it.
     */
    public function apply(Asset $asset, AltRecord $record, array $fields): void;
}
