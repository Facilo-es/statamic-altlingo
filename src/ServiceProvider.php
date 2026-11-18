<?php

namespace Facilo\AltLingo;

use Facilo\AltLingo\Bard\ImageNode;
use Facilo\AltLingo\Http\Middleware\ApiSite;
use Facilo\AltLingo\Store\AltStore;
use Facilo\AltLingo\Store\MetaStore;
use Facilo\AltLingo\Support\Config;
use Facilo\AltLingo\Support\SettingsBlueprint;
use Illuminate\Support\Facades\Event;
use Statamic\Events\AssetSaving;
use Statamic\Fieldtypes\Bard\Augmentor;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $fieldtypes = [
        Fieldtypes\AltLingo::class,
    ];

    protected $commands = [
        Commands\Install::class,
        Commands\Import::class,
        Commands\Uninstall::class,
    ];

    protected $vite = [
        'input' => ['resources/js/addon.js', 'resources/css/addon.css'],
        'publicDirectory' => 'resources/dist',
    ];

    public function register()
    {
        parent::register();

        $this->app->singleton(Config::class);
        $this->app->bind(AltStore::class, MetaStore::class);
        $this->app->singleton(AltLingo::class);
    }

    public function bootAddon()
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'altlingo');

        Event::listen(AssetSaving::class, Listeners\SplitIncomingValue::class);

        Augmentor::replaceExtension('image', new ImageNode);

        $this->app['router']->pushMiddlewareToGroup(config('statamic.api.middleware', 'api'), ApiSite::class);

        $this->registerSettingsBlueprint(fn () => SettingsBlueprint::make(app(Config::class)));
    }
}
