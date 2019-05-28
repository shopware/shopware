<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

class ImageType extends MediaType
{
    public const ANIMATED = 'animated';
    public const TRANSPARENT = 'transparent';
    public const VECTOR_GRAPHIC = 'vectorGraphic';

    protected $name = 'IMAGE';
}
