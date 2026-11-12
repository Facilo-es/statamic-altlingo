<?php

namespace Facilo\AltLingo\Coverage;

use Facilo\AltLingo\AltLingo;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget as BaseWidget;

class Widget extends BaseWidget
{
    protected static $handle = 'altlingo_coverage';

    public static function title()
    {
        return __('altlingo::messages.widget_title');
    }

    public function component()
    {
        if (! User::current()?->can('view altlingo')) {
            return null;
        }

        $totals = app(CoverageCache::class)->totals();
        $sites = collect(app(AltLingo::class)->config->sites())->map(fn ($s) => [
            'handle' => $s,
            'name' => Site::get($s)?->name() ?? $s,
            'percent' => $totals[$s]['percent'],
        ])->values()->all();

        return VueComponent::render('altlingo-coverage-widget', [
            'title' => static::title(),
            'sites' => $sites,
            'url' => cp_route('altlingo.index'),
            'linkText' => __('altlingo::messages.fill_gaps'),
        ]);
    }
}
