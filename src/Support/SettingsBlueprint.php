<?php

namespace Facilo\AltLingo\Support;

use Statamic\Facades\AssetContainer;
use Statamic\Facades\Site;

/**
 * Settings page: Free shows the field and containers plus one Pro card; Pro adds the rest.
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

        if (! $config->isPro()) {
            $sections[] = [
                'display' => 'AltLingo Pro',
                'fields' => [self::field('pro_card', [
                    'type' => 'info',
                    'content' => $t('pro_card').' [statamic.com/addons/facilo/altlingo](https://statamic.com/addons/facilo/altlingo)',
                    'state' => 'tip',
                ])],
            ];

            return ['tabs' => ['main' => ['sections' => $sections]]];
        }

        $main = $config->mainSite();
        $fallbacks = [];
        foreach ($config->sites() as $site) {
            if ($site === $main) {
                continue;
            }
            $name = Site::get($site)?->name() ?? $site;
            $fallbacks[] = self::field("fallback_{$site}", [
                'type' => 'select',
                'display' => $name,
                'instructions' => $t('fallback_help'),
                'multiple' => true,
                'width' => 50,
                'options' => collect($config->sites())->reject(fn ($s) => $s === $site)
                    ->mapWithKeys(fn ($s) => [$s => Site::get($s)?->name() ?? $s])->all(),
            ]);
            $fallbacks[] = self::field("missing_{$site}", [
                'type' => 'button_group',
                'display' => $t('when_missing', ['site' => $name]),
                'width' => 50,
                'default' => 'main',
                'options' => ['main' => $t('use_main'), 'empty' => $t('leave_empty')],
            ]);
        }

        $sections[] = ['display' => $t('fallbacks'), 'fields' => $fallbacks];

        $keyFound = $config->apiKey() !== null;
        $sections[] = [
            'display' => $t('translation'),
            'fields' => [
                self::field('provider', [
                    'type' => 'button_group',
                    'display' => $t('provider'),
                    'default' => 'deepl',
                    'options' => ['deepl' => 'DeepL', 'openai' => $t('openai')],
                ]),
                self::field('key_status', [
                    'type' => 'info',
                    'content' => ($keyFound ? $t('key_found') : $t('no_key')).' (ALTLINGO_DEEPL_KEY / ALTLINGO_OPENAI_KEY)',
                    'state' => $keyFound ? 'success' : 'warning',
                ]),
                self::field('base_url', [
                    'type' => 'text',
                    'display' => $t('base_url'),
                    'placeholder' => 'https://api.openai.com/v1',
                    'width' => 50,
                    'if' => ['provider' => 'equals openai'],
                ]),
                self::field('model', [
                    'type' => 'text',
                    'display' => $t('model'),
                    'placeholder' => 'gpt-4o-mini',
                    'width' => 50,
                    'if' => ['provider' => 'equals openai'],
                ]),
            ],
        ];

        $sections[] = [
            'display' => $t('more_fields'),
            'fields' => [self::field('extra_fields', [
                'type' => 'taggable',
                'display' => $t('extra_fields'),
                'instructions' => $t('extra_fields_help'),
            ])],
        ];

        return ['tabs' => ['main' => ['sections' => $sections]]];
    }

    private static function field(string $handle, array $field): array
    {
        return ['handle' => $handle, 'field' => $field];
    }
}
