<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface;

class MetadataLoader
{
    /**
     * @var MetadataLoaderInterface[]
     */
    private $metadataLoader;

    public function __construct(iterable $metadataLoader)
    {
        $this->metadataLoader = $metadataLoader;
    }

    public function loadFromFile(MediaFile $mediaFile, MediaType $mediaType): ?array
    {
        foreach ($this->metadataLoader as $loader) {
            if ($loader->supports($mediaType)) {
                return $loader->extractMetadata($mediaFile->getFileName());
            }
        }

        return null;
    }
}
