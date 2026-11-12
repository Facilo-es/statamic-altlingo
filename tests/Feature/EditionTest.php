<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Fieldtypes\AltLingo as AltLingoFieldtype;
use Facilo\AltLingo\Support\SettingsBlueprint;
use Facilo\AltLingo\Tests\TestCase;
use Statamic\Facades\Site;
use Statamic\Facades\User;

class EditionTest extends TestCase
{
    private function admin()
    {
        $user = User::make()->email('admin'.uniqid().'@altlingo.test')->makeSuper();
        $user->save();

        return $user;
    }

    private function settingsFields(): array
    {
        $fields = [];
        foreach (SettingsBlueprint::make($this->cfg)['tabs']['main']['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $fields[] = $field['handle'];
            }
        }

        return $fields;
    }

    /** T24: Free keeps the field, resolver and coverage, has no Pro routes, and one upsell card. */
    public function test_t24_free_edition(): void
    {
        $this->cfg->pro = false;
        $this->fixtureAtoD();

        $this->assertSame('Terrace', $this->altlingo()->resolve($this->fresh($this->image('e.png', 'Terraza', ['en' => 'Terrace'])), 'en'));
        $this->assertSame('Terraza', $this->altlingo()->resolve($this->fresh($this->image('f.png', 'Terraza')), 'ca'));
        $this->assertNotEmpty(app(\Facilo\AltLingo\Coverage\CoverageCache::class)->totals());

        $admin = $this->admin();
        foreach (['get' => ['/cp/altlingo', '/cp/altlingo/rows', '/cp/altlingo/export'], 'post' => ['/cp/altlingo/cell', '/cp/altlingo/bulk', '/cp/altlingo/translate', '/cp/altlingo/translate/batch', '/cp/altlingo/import/preview', '/cp/altlingo/import/apply', '/cp/altlingo/translate/image/eA']] as $method => $urls) {
            foreach ($urls as $url) {
                $this->actingAs($admin)->{$method.'Json'}($url)->assertNotFound();
            }
        }

        $fields = $this->settingsFields();
        $this->assertSame(['containers', 'field', 'pro_card'], $fields);
        $this->assertStringContainsString('statamic.com/addons/facilo/altlingo', json_encode(SettingsBlueprint::make($this->cfg), JSON_UNESCAPED_SLASHES));

        $meta = (new AltLingoFieldtype)->preload();
        $this->assertFalse($meta['pro']);
        $this->assertFalse($meta['canTranslate']);
        $this->assertNull($meta['translateUrl']);

        $this->cfg->pro = true;
        $this->assertNotContains('pro_card', $this->settingsFields());
        $this->assertContains('fallback_en', $this->settingsFields());
    }

    /** T25: the addon never checks the licence, so an invalid one changes nothing on the front end. */
    public function test_t25_invalid_licence_changes_nothing(): void
    {
        $this->cfg->settings['containers'] = ['assets'];
        $before = $this->get('/en/')->assertOk()->getContent();

        config(['statamic.system.license_key' => 'invalid-licence-key']);
        $after = $this->get('/en/')->assertOk()->getContent();

        $this->assertSame(preg_replace('/(csrf|nonce)[^"]*"[^"]*"/', '', $before), preg_replace('/(csrf|nonce)[^"]*"[^"]*"/', '', $after));
        foreach (\Illuminate\Support\Facades\File::allFiles(dirname(__DIR__, 2).'/src') as $file) {
            $this->assertDoesNotMatchRegularExpression('/licen[cs]e/i', $file->getContents(), $file->getFilename().' must not check the licence');
        }
    }

    /** T26: with one site the field is a plain text field and the page says why. */
    public function test_t26_single_site(): void
    {
        Site::setSites(['es' => ['name' => 'Español', 'url' => '/', 'locale' => 'es_ES']]);

        $this->assertFalse($this->cfg->isMultisite());
        $meta = (new AltLingoFieldtype)->preload();
        $this->assertCount(1, $meta['sites']);

        $asset = $this->blank('s.png');
        $asset->set('alt', 'Terraza')->save();
        $this->assertSame('Terraza', $this->fresh($asset)->get('alt'));
        $this->assertSame('Terraza', $this->altlingo()->resolve($this->fresh($asset), 'es'));

        $page = $this->actingAs($this->admin())->get('/cp/altlingo')->assertOk()->getContent();
        $this->assertStringContainsString('&quot;multisite&quot;:false', $page);
        $this->assertStringContainsString('single_site', json_encode(__('altlingo::messages')));
    }
}
