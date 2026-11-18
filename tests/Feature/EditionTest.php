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

    /** T24: the free package has every feature, no control panel pages of its own, and a short settings page. */
    public function test_t24_free_package(): void
    {
        $this->fixtureAtoD();

        $this->assertSame('Terrace', $this->altlingo()->resolve($this->fresh($this->image('e.png', 'Terraza', ['en' => 'Terrace'])), 'en'));
        $this->assertSame('Terraza', $this->altlingo()->resolve($this->fresh($this->image('f.png', 'Terraza')), 'ca'));

        $admin = $this->admin();
        foreach (['/cp/altlingo', '/cp/altlingo/rows', '/cp/altlingo/export'] as $url) {
            $this->actingAs($admin)->getJson($url)->assertNotFound();
        }

        $this->assertSame(['containers', 'field'], $this->settingsFields());

        $meta = (new AltLingoFieldtype)->preload();
        foreach (['pro', 'canTranslate', 'translateUrl'] as $key) {
            $this->assertArrayNotHasKey($key, $meta);
        }
    }

    /** The public package holds no paid edition: no Pro code, edition, licence line or text. */
    public function test_no_paid_edition_in_package(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode(file_get_contents($root.'/composer.json'), true);
        $this->assertArrayNotHasKey('editions', $composer['extra']['statamic']);

        $files = [$root.'/composer.json', $root.'/LICENSE.md', $root.'/README.md', $root.'/CHANGELOG.md', $root.'/SECURITY.md'];
        foreach (['src', 'lang', 'resources/js', 'resources/dist'] as $dir) {
            foreach (\Illuminate\Support\Facades\File::allFiles($root.'/'.$dir) as $file) {
                $files[] = $file->getPathname();
            }
        }
        foreach ($files as $file) {
            // "pro" alone is German for "per" in lang/de, so the check looks for the paid features' words instead.
            $this->assertDoesNotMatchRegularExpression('/AltLingo Pro|Pro edition|isPro\b|edition\(|[\'"]pro[\'"]|DeepL|OpenAI|translateUrl|altlingo\.translate|CoverageCache|fallback_|extra_fields/i', file_get_contents($file), basename($file).' must not hold paid code or text');
        }
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
    }
}
