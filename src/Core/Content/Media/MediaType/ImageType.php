<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Content\Media\Metadata\Type\ImageMetadata;
use Shopware\Core\Content\Media\Metadata\Type\MetadataType;

class ImageType extends MediaType
{
    public const ANIMATED = 'animated';
    public const TRANSPARENT = 'transparent';
    public const VECTOR_GRAPHIC = 'vectorGraphic';

    protected $name = 'IMAGE';

    public function getMetadataType(): MetadataType
    {
        return new ImageMetadata();
    }
}
