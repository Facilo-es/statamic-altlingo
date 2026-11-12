<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Store\MemoryStore;

/** T28: the same suite on the in-memory store. */
class CsvMemoryStoreTest extends CsvTest
{
    protected string $store = MemoryStore::class;
}
