<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

class ImageMetadataLoader extends MetadataTypeLoader
{
    public function getValidFileExtensions(): array
    {
        return [
            'jpg',
            'gif',
            'png',
        ];
    }

    public function create(): MetadataType
    {
        return new ImageMetadata();
    }
}
