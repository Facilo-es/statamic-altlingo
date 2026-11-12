<?php

namespace Facilo\AltLingo\Bard;

use Facilo\AltLingo\AltLingo;
use Statamic\Facades\Asset;
use Statamic\Fieldtypes\Bard\ImageNode as Base;

/**
 * Bard copies the raw main text into its images. This gives them the visitor's language.
 */
class ImageNode extends Base
{
    protected function getAlt($id)
    {
        $asset = Asset::find($id);

        return $asset ? app(AltLingo::class)->resolve($asset) : null;
    }
}
