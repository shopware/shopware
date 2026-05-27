<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail\Processor;

use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Thumbnail\DTO\ThumbnailImage;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface ThumbnailProcessorInterface
{
    public function createImageFromString(string $file): ThumbnailImage;

    public function rotate(ThumbnailImage $image, float $angle): ThumbnailImage;

    /**
     * @return int<1, max>
     */
    public function getWidth(ThumbnailImage $image): int;

    /**
     * @return int<1, max>
     */
    public function getHeight(ThumbnailImage $image): int;

    /**
     * @param array{width: int<1, max>, height: int<1, max>} $originalImageSize
     * @param array{width: int<1, max>, height: int<1, max>} $thumbnailSize
     */
    public function createNewImage(ThumbnailImage $mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize): ThumbnailImage;

    public function convertImage(ThumbnailImage $thumbnail, string $mimeType, int $quality): string;
}
