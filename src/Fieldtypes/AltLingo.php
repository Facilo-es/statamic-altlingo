<?php

namespace Facilo\AltLingo\Fieldtypes;

use Facilo\AltLingo\AltLingo as Service;
use Facilo\AltLingo\Store\AltRecord;
use Facilo\AltLingo\Support\Config;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\GraphQL;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Fields\Fieldtype;

class AltLingo extends Fieldtype
{
    protected static $handle = 'altlingo';

    protected $categories = ['text'];

    protected $icon = 'translate';

    public static function title()
    {
        return 'AltLingo';
    }

    private function service(): Service
    {
        return app(Service::class);
    }

    private function asset(): ?Asset
    {
        $parent = $this->field()?->parent();

        return $parent instanceof Asset ? $parent : null;
    }

    public function preProcess($data)
    {
        return is_array($data) ? ($data['main'] ?? null) : $data;
    }

    public function process($data)
    {
        if (! is_array($data)) {
            return $data;
        }

        // The save listener splits this into the plain main text and the other sites.
        return $data + ['__altlingo' => true];
    }

    public function augment($value)
    {
        $value = $this->preProcess($value);

        if (! $asset = $this->asset()) {
            return $value;
        }

        return $this->service()->resolve($asset, null, $this->field()->handle());
    }

    public function preProcessIndex($value)
    {
        return $this->preProcess($value);
    }

    public function preload()
    {
        $config = $this->service()->config;
        $user = User::current();
        $sites = collect($config->sites())->map(fn ($handle) => [
            'handle' => $handle,
            'name' => Site::get($handle)?->name() ?? $handle,
            'lang' => Site::get($handle)?->lang(),
            'main' => $handle === $config->mainSite(),
            'editable' => ! $config->isMultisite() || ! $user || $user->can("access {$handle} site"),
        ])->values()->all();

        $asset = $this->asset();
        $record = $asset ? $this->service()->record($asset) : new AltRecord;
        $handle = $this->field()?->handle() ?? 'alt';
        $resolver = $config->resolver();

        return [
            'sites' => $sites,
            'mainSite' => $config->mainSite(),
            'isAltField' => $handle === $config->field(),
            'pro' => $config->isPro(),
            'canTranslate' => $config->isPro() && (bool) $user?->can('translate altlingo') && $config->translatorConfigured(),
            'record' => [
                'main' => $record->mainText($handle),
                'sites' => $record->texts[$handle] ?? new \stdClass,
                'decorative' => $record->decorative,
                'review' => $record->review,
                'machine' => $record->machine,
            ],
            'fallbacks' => collect($config->sites())->mapWithKeys(fn ($s) => [$s => $resolver->source($record, $s, $handle)])->all(),
            'translateUrl' => $asset && $config->isPro() ? cp_route('altlingo.translate-one', ['image' => base64_encode($asset->id())]) : null,
            'strings' => __('altlingo::fieldtype'),
        ];
    }

    public function toGqlType()
    {
        return [
            'type' => GraphQL::string(),
            'args' => [
                'site' => [
                    'type' => GraphQL::string(),
                    'description' => 'Site handle to resolve the text for. Defaults to the current site.',
                ],
            ],
            'resolve' => function ($asset, $args, $context, $info) {
                if (! $asset instanceof Asset) {
                    return $asset->resolveGqlValue($info->fieldName);
                }

                return $this->service()->resolve($asset, $args['site'] ?? null, $info->fieldName);
            },
        ];
    }

    protected function configFieldItems(): array
    {
        return [];
    }

    public static function isImageField(string $extension): bool
    {
        return Config::isImage($extension);
    }
}
