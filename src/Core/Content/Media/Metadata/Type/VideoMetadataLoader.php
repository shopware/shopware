<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

class VideoMetadataLoader extends MetadataTypeLoader
{
    public function getValidFileExtensions(): array
    {
        return [
            'mp4',
            'avi',
            'webm',
        ];
    }

    public function create(): MetadataType
    {
        return new VideoMetadata();
    }
}
