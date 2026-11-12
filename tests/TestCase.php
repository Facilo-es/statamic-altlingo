<?php

namespace Facilo\AltLingo\Tests;

use Facilo\AltLingo\AltLingo;
use Facilo\AltLingo\Coverage\CoverageCache;
use Facilo\AltLingo\Store\AltRecord;
use Facilo\AltLingo\Store\AltStore;
use Facilo\AltLingo\Store\MetaStore;
use Facilo\AltLingo\Support\Blueprints;
use Facilo\AltLingo\Support\Config;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blink;
use Statamic\Facades\Stache;

abstract class TestCase extends BaseTestCase
{
    /** A 1×1 PNG, so Statamic can read real image metadata. */
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected TestConfig $cfg;

    protected string $container;

    /** Set by T28 to run a whole test class on the in-memory store. */
    protected string $store = MetaStore::class;

    public function createApplication()
    {
        $app = require (getenv('ALTLINGO_TEST_APP') ?: '/tmp/altlingo-test').'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->cfg = new TestConfig;
        $this->app->instance(Config::class, $this->cfg);
        $this->app->singleton(AltStore::class, $this->store);
        $this->app->forgetInstance(AltLingo::class);
        $this->app->forgetInstance(CoverageCache::class);

        $this->container = 't'.substr(md5(uniqid('', true)), 0, 8);
        $root = storage_path('altlingo-tests/'.$this->container);
        File::ensureDirectoryExists($root);
        config(["filesystems.disks.{$this->container}" => ['driver' => 'local', 'root' => $root, 'url' => '/'.$this->container]]);

        $container = AssetContainer::make($this->container)->disk($this->container)->title('Tests');
        $container->save();
        Blueprints::setFieldType($container, 'alt', 'altlingo');
        $this->cfg->settings['containers'] = [$this->container];
    }

    protected function tearDown(): void
    {
        // Containers are saved as files, and their disk only exists in this test's config.
        AssetContainer::find($this->container)?->delete();
        File::deleteDirectory(storage_path('altlingo-tests/'.$this->container));

        parent::tearDown();
    }

    protected function altlingo(): AltLingo
    {
        return $this->app->make(AltLingo::class);
    }

    /**
     * An image with its alt data, written through the store like the addon does.
     */
    protected function image(string $name, ?string $main = null, array $texts = [], bool $decorative = false, array $review = []): AssetContract
    {
        $asset = $this->blank($name);
        $record = new AltRecord(main: ['alt' => $main], texts: $texts ? ['alt' => $texts] : [], decorative: $decorative, review: $review);
        $this->altlingo()->save($asset, $record);

        return Asset::find($this->container.'::'.$name) ?? $asset;
    }

    protected function blank(string $name): AssetContract
    {
        File::put(storage_path('altlingo-tests/'.$this->container.'/'.$name), base64_decode(self::PNG));
        $asset = Asset::make()->container($this->container)->path($name);
        $asset->saveQuietly();

        return $asset;
    }

    /** Fixture from spec §6: cases A to D. */
    protected function fixtureAtoD(): array
    {
        return [
            'A' => $this->image('a.png', 'Terraza', ['en' => 'Terrace']),
            'B' => $this->image('b.png', 'Logo', [], true),
            'C' => $this->image('c.png', 'Terraza'),
            'D' => $this->image('d.png', '', ['en' => 'Terrace']),
        ];
    }

    protected function proSettingsForExamples(): void
    {
        $this->cfg->settings['fallback_es_mx'] = ['es'];
        $this->cfg->settings['missing_ca'] = 'empty';
    }

    protected function meta(AssetContract $asset): array
    {
        return $this->fresh($asset)->data()->all();
    }

    /** The asset as saved, not the copy Statamic keeps for the current request. */
    /** Texts of the other sites read back through the store, sorted by site, so tests run on any store. */
    protected function texts(AssetContract $asset): array
    {
        $texts = $this->altlingo()->record($this->fresh($asset))->texts['alt'] ?? [];
        ksort($texts);

        return $texts;
    }

    protected function fresh(AssetContract $asset): AssetContract
    {
        Blink::flush();

        return Asset::find($asset->id());
    }
}
