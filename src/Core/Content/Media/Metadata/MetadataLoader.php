<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata;

use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
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

    public function loadFromFile(MediaFile $mediaFile, MediaType $mediaType): Metadata
    {
        $rawMetadata = [];

        foreach ($this->metadataLoader as $metadataLoader) {
            try {
                $rawMetadata[\get_class($metadataLoader)] = $metadataLoader
                        ->extractMetadata($mediaFile->getFileName());
            } catch (CanNotLoadMetadataException $e) {
                // nth.
            }
        }

        $metadata = new Metadata();
        $metadata->setRawMetadata($this->convertBinaryToUtf($rawMetadata));
        $metadata->setType($mediaType->getMetadataType());

        return $metadata;
    }

    public function updateMetadata(Metadata $metadata): void
    {
        $rawData = $metadata->getRawMetadata();

        foreach ($this->metadataLoader as $metadataLoader) {
            $loaderClass = \get_class($metadataLoader);

            if (!isset($rawData[$loaderClass])) {
                continue;
            }

            $metadataLoader->enhanceTypeObject($metadata->getType(), $rawData[$loaderClass]);
        }
    }

    private function convertBinaryToUtf(array $array): array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->convertBinaryToUtf($value);
            }

            if (\is_string($value) && !mb_detect_encoding($value, mb_detect_order(), true)) {
                $array[$key] = utf8_encode($value);
            }
        }

        return $array;
    }
}
