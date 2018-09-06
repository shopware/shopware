<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\Type\MetadataType;

interface MetadataLoaderInterface
{
    /**
     * @throws CanNotLoadMetadataException
     */
    public function extractMetadata(string $filePath): array;

    public function enhanceTypeObject(MetadataType $metadataType, array $rawMetadata): void;
}
