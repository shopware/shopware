<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use Shopware\Core\Content\Media\MediaType\MediaType;

interface MetadataLoaderInterface
{
    public function extractMetadata(string $filePath): ?array;

    public function supports(MediaType $mediaType): bool;
}
