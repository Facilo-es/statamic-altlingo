<?php

namespace Facilo\AltLingo\Support;

use Statamic\Facades\AssetContainer;

/**
 * Settings page: which containers are covered and which field holds the alt text.
 */
class SettingsBlueprint
{
    public static function make(Config $config): array
    {
        $t = fn ($key, $replace = []) => __("altlingo::settings.{$key}", $replace);

        $sections = [[
            'display' => $t('images'),
            'fields' => [
                self::field('containers', [
                    'type' => 'select',
                    'display' => $t('containers'),
                    'instructions' => $t('containers_help'),
                    'multiple' => true,
                    'options' => AssetContainer::all()->mapWithKeys(fn ($c) => [$c->handle() => $c->title()])->all(),
                ]),
                self::field('field', [
                    'type' => 'text',
                    'display' => $t('field'),
                    'instructions' => $t('field_help'),
                    'placeholder' => 'alt',
                ]),
            ],
        ]];

        return ['tabs' => ['main' => ['sections' => $sections]]];
    }

    private static function field(string $handle, array $field): array
    {
        return ['handle' => $handle, 'field' => $field];
    }
}
