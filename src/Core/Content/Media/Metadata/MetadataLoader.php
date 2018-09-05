<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface;
use Shopware\Core\Content\Media\Metadata\Type\MetadataType;
use Shopware\Core\Content\Media\Metadata\Type\MetadataTypeLoader;
use Shopware\Core\Content\Media\Metadata\Type\NoMetadata;

class MetadataLoader
{
    /**
     * @var MetadataLoaderInterface[]
     */
    private $metadataLoader;

    /**
     * @var MetadataTypeLoader[]
     */
    private $typeLoaders;

    public function __construct(iterable $metadataLoader, iterable $typeLoaders)
    {
        $this->metadataLoader = $metadataLoader;
        $this->typeLoaders = $typeLoaders;
    }

    public function loadFromFile(MediaFile $mediaFile): Metadata
    {
        $rawMetadata = [];

        foreach ($this->metadataLoader as $metadataLoader) {
            try {
                $rawMetadata[get_class($metadataLoader)] = $metadataLoader
                        ->extractMetadata($mediaFile->getFileName());
            } catch (CanNotLoadMetadataException $e) {
                // nth.
            }
        }

        $type = $this->determineMetadataType($mediaFile);

        $metadata = new Metadata();
        $metadata->setRawMetadata($this->convertBinaryToUtf($rawMetadata));
        $metadata->setType($type);

        return $metadata;
    }

    public function updateMetadata(Metadata $metadata): void
    {
        $rawData = $metadata->getRawMetadata();

        foreach ($this->metadataLoader as $metadataLoader) {
            $loaderClass = get_class($metadataLoader);

            if (!isset($rawData[$loaderClass])) {
                continue;
            }

            $metadataLoader
                ->enhanceTypeObject($metadata->getType(), $rawData[$loaderClass]);
        }
    }

    private function determineMetadataType(MediaFile $mediaFile): MetadataType
    {
        foreach ($this->typeLoaders as $typeLoader) {
            if (in_array($mediaFile->getFileExtension(), $typeLoader->getValidFileExtensions(), true)) {
                return $typeLoader->create();
            }
        }

        return NoMetadata::create();
    }

    private function convertBinaryToUtf(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->convertBinaryToUtf($value);
            }

            if (is_string($value)) {
                if (!mb_detect_encoding($value, mb_detect_order(), true)) {
                    $array[$key] = utf8_encode($value);
                }
            }
        }

        return $array;
    }
}
