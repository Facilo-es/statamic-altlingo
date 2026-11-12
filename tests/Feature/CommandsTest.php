<?php

namespace Facilo\AltLingo\Tests\Feature;

use Facilo\AltLingo\Support\Blueprints;
use Facilo\AltLingo\Tests\TestCase;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;

class CommandsTest extends TestCase
{
    private function container()
    {
        return AssetContainer::find($this->container);
    }

    /** T8: dry run changes nothing, real run converts, second run has nothing to do. */
    public function test_t8_install(): void
    {
        Blueprints::setFieldType($this->container(), 'alt', 'text');
        $asset = $this->image('a.png', 'Terraza');
        $before = $this->meta($asset);

        $this->artisan('altlingo:install', ['--dry-run' => true, '--container' => [$this->container]])
            ->expectsOutputToContain('Dry run: no file was changed.')->assertSuccessful();
        $this->assertSame('text', Blueprints::fieldType($this->container(), 'alt'));

        $this->artisan('altlingo:install', ['--force' => true, '--container' => [$this->container]])->assertSuccessful();
        $this->assertSame('altlingo', Blueprints::fieldType($this->container(), 'alt'));
        $this->assertSame($before, $this->meta($asset));

        $this->artisan('altlingo:install', ['--force' => true, '--container' => [$this->container]])
            ->expectsOutputToContain('Nothing to do')->assertSuccessful();
    }

    /** T9: 20 images with alt_en/alt_ca, 40 texts moved, main untouched, existing texts kept. */
    public function test_t9_import_fields(): void
    {
        $assets = [];
        for ($i = 0; $i < 20; $i++) {
            $asset = $this->image("i{$i}.png", "Foto $i", $i === 0 ? ['en' => 'Kept'] : []);
            $asset->set('alt_en', "Photo $i")->set('alt_ca', "Foto ca $i")->saveQuietly();
            $assets[] = $asset;
        }

        $this->artisan('altlingo:import', ['--from' => 'fields'])
            ->expectsTable(['Imported', 'Skipped (text exists)', 'Empty'], [[39, 1, 20]])->assertSuccessful();

        $this->assertSame('Kept', $this->altlingo()->resolve($assets[0], 'en'));
        $this->assertSame('Photo 5', $this->altlingo()->resolve($assets[5], 'en'));
        $this->assertSame('Foto ca 5', $this->altlingo()->resolve($assets[5], 'ca'));
        $this->assertSame('Foto 5', Asset::find($assets[5]->id())->get('alt'));

        $this->artisan('altlingo:import', ['--from' => 'fields', '--overwrite' => true])
            ->expectsTable(['Imported', 'Skipped (text exists)', 'Empty'], [[40, 0, 20]])->assertSuccessful();
        $this->assertSame('Photo 0', $this->altlingo()->resolve($this->fresh($assets[0]), 'en'));
    }

    /** T10: Multisite Any Field data lands under the right sites. */
    public function test_t10_import_daun(): void
    {
        $asset = $this->blank('d.png');
        $asset->set('alt', ['es' => 'Terraza', 'en' => 'Terrace', 'ca' => 'Terrassa'])->saveQuietly();

        $this->artisan('altlingo:import', ['--from' => 'daun'])->assertSuccessful();

        $data = $this->meta($asset);
        $this->assertSame('Terraza', $data['alt']);
        $this->assertSame(['ca' => 'Terrassa', 'en' => 'Terrace'], $data['alt_altlingo']);
    }

    /** T11: uninstall gives back a plain text field and keeps the texts; --purge removes them. It changes every container, so tools/test.sh runs it in its own copy of the site. */
    #[\PHPUnit\Framework\Attributes\Group('uninstall')]
    public function test_t11_uninstall(): void
    {
        $asset = $this->image('u.png', 'Terraza', ['en' => 'Terrace'], false, ['en']);

        $this->artisan('altlingo:uninstall', ['--force' => true])->assertSuccessful();
        $this->assertSame('text', Blueprints::fieldType($this->container(), 'alt'));
        $fresh = Asset::find($asset->id());
        $this->assertSame('Terraza', (string) $fresh->augmentedValue('alt'));
        $this->assertSame(['en' => 'Terrace'], $fresh->get('alt_altlingo'));

        Blueprints::setFieldType($this->container(), 'alt', 'altlingo');
        $this->artisan('altlingo:uninstall', ['--force' => true, '--purge' => true])->assertSuccessful();
        $data = $this->meta($asset);
        $this->assertSame('Terraza', $data['alt']);
        $this->assertArrayNotHasKey('alt_altlingo', $data);
        $this->assertArrayNotHasKey('altlingo', $data);
    }
}
