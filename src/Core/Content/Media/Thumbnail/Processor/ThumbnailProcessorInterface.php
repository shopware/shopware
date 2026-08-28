<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail\Processor;

use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:THUMBNAIL_PROCESSORS
 */
#[Package('discovery')]
interface ThumbnailProcessorInterface
{
    public function createImageFromString(string $file): object;

    public function rotate(object $image, float $angle): object;

    /**
     * @return int<1, max>
     */
    public function getWidth(object $image): int;

    /**
     * @return int<1, max>
     */
    public function getHeight(object $image): int;

    /**
     * @param array{width: int<1, max>, height: int<1, max>} $originalImageSize
     * @param array{width: int<1, max>, height: int<1, max>} $thumbnailSize
     */
    public function createNewImage(object $mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize): object;

    public function convertImage(object $thumbnail, string $mimeType, int $quality): string;
}
