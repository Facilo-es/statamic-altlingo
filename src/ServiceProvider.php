<?php

namespace Facilo\AltLingo;

use Facilo\AltLingo\Bard\ImageNode;
use Facilo\AltLingo\Coverage\CoverageCache;
use Facilo\AltLingo\Http\Middleware\ApiSite;
use Facilo\AltLingo\Store\AltStore;
use Facilo\AltLingo\Store\MetaStore;
use Facilo\AltLingo\Support\Config;
use Facilo\AltLingo\Support\SettingsBlueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Statamic\Events\AssetDeleted;
use Statamic\Events\AssetSaved;
use Statamic\Events\AssetSaving;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
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
        Commands\Refresh::class,
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
        $this->app->singleton(CoverageCache::class);
    }

    public function bootAddon()
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'altlingo');

        Event::listen(AssetSaving::class, Listeners\SplitIncomingValue::class);
        Event::listen(AssetSaved::class, [Listeners\UpdateCoverage::class, 'saved']);
        Event::listen(AssetDeleted::class, [Listeners\UpdateCoverage::class, 'deleted']);

        Augmentor::replaceExtension('image', new ImageNode);

        $this->app['router']->pushMiddlewareToGroup(config('statamic.api.middleware', 'api'), ApiSite::class);

        $this->registerSettingsBlueprint(fn () => SettingsBlueprint::make(app(Config::class)));

        $this->registerPermissions();

        if (app(Config::class)->isPro()) {
            $this->bootPro();
        }
    }

    private function registerPermissions(): void
    {
        Permission::extend(function () {
            Permission::group('altlingo', 'AltLingo', function () {
                Permission::register('view altlingo', function ($permission) {
                    $permission->label(__('altlingo::messages.perm_view'))->children([
                        Permission::make('edit altlingo')->label(__('altlingo::messages.perm_edit'))->children([
                            Permission::make('translate altlingo')->label(__('altlingo::messages.perm_translate')),
                        ]),
                    ]);
                });
                Permission::register('configure altlingo')->label(__('altlingo::messages.perm_configure'));
            });
        });
    }

    private function bootPro(): void
    {
        Coverage\Widget::register();

        $this->registerCpRoutes(function () {
            require __DIR__.'/../routes/cp.php';
        });

        Nav::extend(function ($nav) {
            $nav->content(__('altlingo::messages.nav'))
                ->route('altlingo.index')
                ->icon('translate')
                ->can('view altlingo');
        });
    }
}
