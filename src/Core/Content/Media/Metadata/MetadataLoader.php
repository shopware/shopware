<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Handler;

/**
 * @Handler(
 *     servcieTag="shopware.metadata.loader",
 *     handlerInterface="MetadataLoaderInterface"
 * )
 */
#[Package('content')]
class MetadataLoader
{
    /**
     * @internal
     *
     * @param MetadataLoaderInterface[] $metadataLoader
     */
    public function __construct(private readonly iterable $metadataLoader)
    {
    }

    public function loadFromFile(MediaFile $mediaFile, MediaType $mediaType): ?array
    {
        foreach ($this->metadataLoader as $loader) {
            if ($loader->supports($mediaType)) {
                $metaData = $loader->extractMetadata($mediaFile->getFileName());

                if ($mediaFile->getHash()) {
                    $metaData['hash'] = $mediaFile->getHash();
                }

                return $metaData;
            }
        }

        return null;
    }
}
