<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
interface MetadataLoaderInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function extractMetadata(string $filePath): ?array;

    public function supports(MediaType $mediaType): bool;
}
