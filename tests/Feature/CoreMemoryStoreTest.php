<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Store\MemoryStore;

/** T28: the same suite on the in-memory store. */
class CoreMemoryStoreTest extends CoreTest
{
    protected string $store = MemoryStore::class;

    /** T3 on this store: the same texts, read back through the store instead of the meta file. */
    public function test_t3_save_splits_panel_value(): void
    {
        $asset = $this->blank('p.png');
        $asset->set('alt', ['__altlingo' => true, 'main' => ' Terraza ', 'sites' => ['en' => 'Terrace', 'ca' => 'Terrassa', 'es_mx' => '  '], 'decorative' => false]);
        $asset->save();

        $record = $this->altlingo()->record($this->fresh($asset));
        $this->assertSame('Terraza', $record->mainText());
        $this->assertSame(['ca' => 'Terrassa', 'en' => 'Terrace'], $this->texts($asset));
        $this->assertSame('Terraza', $this->fresh($asset)->get('alt'));
    }
}
