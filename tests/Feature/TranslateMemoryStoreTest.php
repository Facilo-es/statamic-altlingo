<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Store\MemoryStore;

/** T28: the same suite on the in-memory store. */
class TranslateMemoryStoreTest extends TranslateTest
{
    protected string $store = MemoryStore::class;
}
