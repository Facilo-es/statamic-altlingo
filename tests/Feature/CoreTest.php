<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Coverage\CoverageCache;
use Facilo\AltLingo\Resolver;
use Facilo\AltLingo\Tests\TestCase;
use Statamic\Facades\Asset;

class CoreTest extends TestCase
{
    /** T1: the worked examples of spec §6, every site. */
    public function test_t1_resolver_worked_examples(): void
    {
        $this->proSettingsForExamples();
        $img = $this->fixtureAtoD();
        $al = $this->altlingo();

        $expected = [
            'A' => ['es' => ['Terraza', 'done'], 'en' => ['Terrace', 'done'], 'ca' => ['', 'missing'], 'es_mx' => ['Terraza', 'fallback']],
            'B' => ['es' => ['', 'decorative'], 'en' => ['', 'decorative'], 'ca' => ['', 'decorative'], 'es_mx' => ['', 'decorative']],
            'C' => ['es' => ['Terraza', 'done'], 'en' => ['Terraza', 'fallback'], 'ca' => ['', 'missing'], 'es_mx' => ['Terraza', 'fallback']],
            'D' => ['es' => ['', 'missing'], 'en' => ['Terrace', 'done'], 'ca' => ['', 'missing'], 'es_mx' => ['', 'missing']],
        ];

        // Case A's ca follows the "Leave empty" set for case C; without it A's ca falls back to the main text.
        foreach ($expected as $case => $sites) {
            $statuses = $al->statuses($img[$case]);
            foreach ($sites as $site => [$text, $status]) {
                $this->assertSame($text, $al->resolve($img[$case], $site), "$case $site text");
                $this->assertSame($status, $statuses[$site], "$case $site status");
            }
        }

        unset($this->cfg->settings['missing_ca']);
        $this->assertSame('Terraza', $al->resolve($img['A'], 'ca'));
        $this->assertSame(Resolver::FALLBACK, $al->statuses($img['A'])['ca']);
    }

    /** T2: coverage of fixture A to D. */
    public function test_t2_coverage(): void
    {
        $this->fixtureAtoD();
        $totals = $this->app->make(CoverageCache::class)->totals();

        $this->assertSame(75, $totals['es']['percent']);
        $this->assertSame(75, $totals['en']['percent']);
        $this->assertSame(25, $totals['ca']['percent']);
        $this->assertSame(25, $totals['es_mx']['percent']);
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

    /** T12: decorative means alt="" everywhere, and counts as done. */
    public function test_t12_decorative(): void
    {
        $asset = $this->image('logo.png', 'Logo', ['en' => 'Logo'], true);

        foreach (['es', 'en', 'ca', 'es_mx'] as $site) {
            $this->assertSame('', $this->altlingo()->resolve($asset, $site));
        }
        $totals = $this->app->make(CoverageCache::class)->totals();
        $this->assertSame(100, $totals['ca']['percent']);
    }

    /** T23: fallback chain, then Leave empty. */
    public function test_t23_fallback_chain(): void
    {
        $asset = $this->image('a.png', 'Terraza', ['en' => 'Terrace']);
        $this->cfg->settings['fallback_es_mx'] = ['es'];
        $this->assertSame('Terraza', $this->altlingo()->resolve($asset, 'es_mx'));
        $this->assertSame(Resolver::FALLBACK, $this->altlingo()->statuses($asset)['es_mx']);

        unset($this->cfg->settings['fallback_es_mx']);
        $this->cfg->settings['missing_es_mx'] = 'empty';
        $this->assertSame('', $this->altlingo()->resolve($asset, 'es_mx'));
        $this->assertSame(Resolver::MISSING, $this->altlingo()->statuses($asset)['es_mx']);
    }

    /** Free behaves as "Use the main language" whatever the Pro settings say. */
    public function test_free_ignores_pro_fallback_settings(): void
    {
        $this->cfg->pro = false;
        $this->cfg->settings['missing_ca'] = 'empty';
        $asset = $this->image('a.png', 'Terraza');

        $this->assertSame('Terraza', $this->altlingo()->resolve($asset, 'ca'));
    }
}
