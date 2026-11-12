<?php

namespace Facilo\AltLingo\Support;

use Statamic\Contracts\Assets\AssetContainer;

/**
 * Reads and changes the type of one field in an asset container's blueprint.
 */
class Blueprints
{
    public static function fieldType(AssetContainer $container, string $field): ?string
    {
        return $container->blueprint()->field($field)?->type();
    }

    /** Sets the field's type, adding the field when the blueprint doesn't have it. */
    public static function setFieldType(AssetContainer $container, string $field, string $type): void
    {
        $blueprint = $container->blueprint();
        $contents = $blueprint->contents();
        $found = false;

        foreach ($contents['tabs'] ?? [] as $t => $tab) {
            foreach ($tab['sections'] ?? [] as $s => $section) {
                foreach ($section['fields'] ?? [] as $f => $item) {
                    if (($item['handle'] ?? null) === $field && is_array($item['field'] ?? null)) {
                        $contents['tabs'][$t]['sections'][$s]['fields'][$f]['field']['type'] = $type;
                        $found = true;
                    }
                }
            }
        }

        if ($found) {
            $blueprint->setContents($contents);
        } else {
            $blueprint->ensureField($field, ['type' => $type, 'display' => 'Alt Text']);
        }

        $blueprint->save();
    }
}
