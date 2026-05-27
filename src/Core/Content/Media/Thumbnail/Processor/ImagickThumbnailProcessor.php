<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail\Processor;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Thumbnail\DTO\ThumbnailImage;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class ImagickThumbnailProcessor implements ThumbnailProcessorInterface
{
    public function createImageFromString(string $file): ThumbnailImage
    {
        $image = new \Imagick();
        $image->readImageBlob($file);

        return new ThumbnailImage($image);
    }

    public function rotate(ThumbnailImage $image, float $angle): ThumbnailImage
    {
        \assert($image->image instanceof \Imagick);

        // GD rotates counter-clockwise; Imagick rotates clockwise, so negate the angle
        $image->image->rotateImage(new \ImagickPixel('black'), -$angle);

        return $image;
    }

    public function getWidth(ThumbnailImage $image): int
    {
        \assert($image->image instanceof \Imagick);

        $width = $image->image->getImageWidth();

        \assert($width > 0);

        return $width;
    }

    public function getHeight(ThumbnailImage $image): int
    {
        \assert($image->image instanceof \Imagick);

        $height = $image->image->getImageHeight();

        \assert($height > 0);

        return $height;
    }

    public function createNewImage(ThumbnailImage $mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize): ThumbnailImage
    {
        \assert($mediaImage->image instanceof \Imagick);

        $thumbnail = clone $mediaImage->image;
        $thumbnail->resizeImage(
            $thumbnailSize['width'],
            $thumbnailSize['height'],
            \Imagick::FILTER_LANCZOS,
            1
        );

        if (!$type->is(ImageType::TRANSPARENT)) {
            $background = new \Imagick();
            $background->newImage($thumbnailSize['width'], $thumbnailSize['height'], new \ImagickPixel('white'));
            $background->setImageFormat($thumbnail->getImageFormat());
            $background->compositeImage($thumbnail, \Imagick::COMPOSITE_OVER, 0, 0);
            $thumbnail->clear();

            return new ThumbnailImage($background);
        }

        return new ThumbnailImage($thumbnail);
    }

    public function convertImage(ThumbnailImage $thumbnail, string $mimeType, int $quality): string
    {
        \assert($thumbnail->image instanceof \Imagick);

        switch ($mimeType) {
            case 'image/png':
                $thumbnail->image->setImageFormat('png');

                break;
            case 'image/gif':
                $thumbnail->image->setImageFormat('gif');

                break;
            case 'image/jpg':
            case 'image/jpeg':
                $thumbnail->image->setImageFormat('jpeg');
                $thumbnail->image->setImageCompressionQuality($quality);

                break;
            case 'image/webp':
                if (!\in_array('WEBP', \Imagick::queryFormats('WEBP'), true)) {
                    throw MediaException::cannotCreateImage();
                }

                $thumbnail->image->setImageFormat('webp');
                $thumbnail->image->setImageCompressionQuality($quality);

                break;
            case 'image/avif':
                if (!\in_array('AVIF', \Imagick::queryFormats('AVIF'), true)) {
                    throw MediaException::cannotCreateImage();
                }

                $thumbnail->image->setImageFormat('avif');
                $thumbnail->image->setImageCompressionQuality($quality);

                break;
        }

        return $thumbnail->image->getImageBlob();
    }
}
