<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Resolver;
use Facilo\AltLingo\Tests\TestCase;
use Statamic\Facades\Asset;

class CoreTest extends TestCase
{
    /** T1: the worked examples of spec §6, every site: a missing text uses the main language. */
    public function test_t1_resolver_worked_examples(): void
    {
        $img = $this->fixtureAtoD();
        $al = $this->altlingo();

        $expected = [
            'A' => ['es' => ['Terraza', 'done'], 'en' => ['Terrace', 'done'], 'ca' => ['Terraza', 'fallback'], 'es_mx' => ['Terraza', 'fallback']],
            'B' => ['es' => ['', 'decorative'], 'en' => ['', 'decorative'], 'ca' => ['', 'decorative'], 'es_mx' => ['', 'decorative']],
            'C' => ['es' => ['Terraza', 'done'], 'en' => ['Terraza', 'fallback'], 'ca' => ['Terraza', 'fallback'], 'es_mx' => ['Terraza', 'fallback']],
            'D' => ['es' => ['', 'missing'], 'en' => ['Terrace', 'done'], 'ca' => ['', 'missing'], 'es_mx' => ['', 'missing']],
        ];

        foreach ($expected as $case => $sites) {
            $statuses = $al->statuses($img[$case]);
            foreach ($sites as $site => [$text, $status]) {
                $this->assertSame($text, $al->resolve($img[$case], $site), "$case $site text");
                $this->assertSame($status, $statuses[$site], "$case $site status");
            }
        }
    }

    /** T3: texts typed in the panel are split on save. */
    public function test_t3_save_splits_panel_value(): void
    {
        $asset = $this->blank('p.png');
        $asset->set('title', 'Keep me');
        $asset->set('alt', ['__altlingo' => true, 'main' => ' Terraza ', 'sites' => ['en' => 'Terrace', 'ca' => 'Terrassa', 'es_mx' => '  '], 'decorative' => false]);
        $asset->save();

        $data = $this->meta($asset);
        $this->assertSame('Terraza', $data['alt']);
        $this->assertSame(['ca' => 'Terrassa', 'en' => 'Terrace'], $data['alt_altlingo']);
        $this->assertSame('Keep me', $data['title']);
        $this->assertArrayNotHasKey('altlingo', $data);
    }

    /** T7: PHP code reading the raw value gets the main string. */
    public function test_t7_raw_value_is_main_string(): void
    {
        $asset = $this->image('r.png', 'Terraza', ['en' => 'Terrace']);

        $this->assertSame('Terraza', Asset::find($asset->id())->get('alt'));
    }

    /** T12: decorative means alt="" everywhere. */
    public function test_t12_decorative(): void
    {
        $asset = $this->image('logo.png', 'Logo', ['en' => 'Logo'], true);

        foreach (['es', 'en', 'ca', 'es_mx'] as $site) {
            $this->assertSame('', $this->altlingo()->resolve($asset, $site));
        }        $this->assertSame(Resolver::DECORATIVE, $this->altlingo()->statuses($asset)['ca']);
    }

    /** A missing text always uses the main language, whatever settings an older version left behind. */
    public function test_missing_text_uses_main_language(): void
    {
        $this->cfg->settings['missing_ca'] = 'empty';
        $this->cfg->settings['fallback_es_mx'] = ['en'];
        $asset = $this->image('a.png', 'Terraza', ['en' => 'Terrace']);

        $this->assertSame('Terraza', $this->altlingo()->resolve($asset, 'ca'));
        $this->assertSame('Terraza', $this->altlingo()->resolve($asset, 'es_mx'));
        $this->assertSame(Resolver::FALLBACK, $this->altlingo()->statuses($asset)['es_mx']);
    }
}
