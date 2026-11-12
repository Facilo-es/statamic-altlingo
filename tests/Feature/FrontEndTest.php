<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Tests\TestCase;
use Statamic\Facades\Asset;

/**
 * T4 to T6, on the demo site's page and seeded images (container "assets").
 */
class FrontEndTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cfg->settings['containers'] = ['assets'];
    }

    private function alts(string $html, string $pattern): array
    {
        $part = explode('data-pattern="'.$pattern.'"', $html, 2)[1] ?? '';
        $part = explode('data-pattern=', $part, 2)[0];
        preg_match_all('/<img [^>]*alt="([^"]*)"/', $part, $m);

        return array_map(fn ($a) => html_entity_decode($a, ENT_QUOTES), $m[1]);
    }

    /** T4: one entry, three sites, three different alt attributes in every template pattern. */
    public function test_t4_front_end_alt_per_site(): void
    {
        $hero = Asset::find('assets::fotos/p0071.jpg');
        $seen = [];

        foreach (['es' => '/', 'en' => '/en/', 'ca' => '/ca/'] as $site => $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $expected = $this->altlingo()->resolve($hero, $site);
            $this->assertNotSame('', $expected);
            $this->assertSame($expected, $this->alts($html, 'asset-pair')[0] ?? null, "{{ alt }} in $site");
            $this->assertSame($expected, $this->alts($html, 'glide-pair')[0] ?? null, "glide pair in $site");
            $seen[] = $expected;
        }

        $this->assertCount(3, array_unique($seen));
    }

    /** T5: a Bard image with no alt of its own takes the site's text. */
    public function test_t5_bard_image(): void
    {
        $html = $this->get('/en/')->assertOk()->getContent();
        $asset = Asset::find('assets::fotos/p0000.jpg');
        $en = $this->altlingo()->resolve($asset, 'en');

        $this->assertNotSame($this->altlingo()->resolve($asset, 'es'), $en);
        $this->assertSame([$en], $this->alts($html, 'bard'));
    }

    /** T6: REST with ?site=en, and GraphQL alt(site: "ca"). */
    public function test_t6_rest_and_graphql(): void
    {
        $asset = Asset::find('assets::fotos/p0071.jpg');

        $rest = $this->getJson('/api/assets/assets/fotos/p0071.jpg?site=en')->assertOk();
        $this->assertSame($this->altlingo()->resolve($asset, 'en'), $rest->json('data.alt'));

        $gql = $this->postJson('/graphql', ['query' => '{ asset(container: "assets", path: "fotos/p0071.jpg") { ... on Asset_Assets { alt ca: alt(site: "ca") } } }'])->assertOk();
        $this->assertSame($this->altlingo()->resolve($asset, 'es'), $gql->json('data.asset.alt'));
        $this->assertSame($this->altlingo()->resolve($asset, 'ca'), $gql->json('data.asset.ca'));
        $this->assertNotSame($gql->json('data.asset.alt'), $gql->json('data.asset.ca'));
    }
}
