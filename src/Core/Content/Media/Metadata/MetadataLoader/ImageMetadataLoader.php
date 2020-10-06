<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use FastImageSize\FastImageSize;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;

class ImageMetadataLoader implements MetadataLoaderInterface
{
    /**
     * @var FastImageSize
     */
    private $fastImage;

    public function __construct()
    {
        $this->fastImage = new FastImageSize();
    }

    public function extractMetadata(string $filePath): ?array
    {
        $metadata = $this->fastImage->getImageSize($filePath);
        if (\is_array($metadata)) {
            return $metadata;
        }

        return null;
    }

    public function supports(MediaType $mediaType): bool
    {
        return $mediaType instanceof ImageType;
    }
}
