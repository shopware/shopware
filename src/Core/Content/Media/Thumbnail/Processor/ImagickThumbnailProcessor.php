<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail\Processor;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class ImagickThumbnailProcessor implements ThumbnailProcessorInterface
{
    public function createImageFromString(string $file): \Imagick
    {
        $image = new \Imagick();
        $image->readImageBlob($file);

        return $image;
    }

    public function rotate(object $image, float $angle): \Imagick
    {
        \assert($image instanceof \Imagick);

        // GD rotates counter-clockwise; Imagick rotates clockwise, so negate the angle
        $image->rotateImage(new \ImagickPixel('black'), -$angle);

        return $image;
    }

    public function getWidth(object $image): int
    {
        \assert($image instanceof \Imagick);

        $width = $image->getImageWidth();

        \assert($width > 0);

        return $width;
    }

    public function getHeight(object $image): int
    {
        \assert($image instanceof \Imagick);

        $height = $image->getImageHeight();

        \assert($height > 0);

        return $height;
    }

    public function createNewImage(object $mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize): \Imagick
    {
        \assert($mediaImage instanceof \Imagick);

        $thumbnail = clone $mediaImage;
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

            return $background;
        }

        return $thumbnail;
    }

    public function convertImage(object $thumbnail, string $mimeType, int $quality): string
    {
        \assert($thumbnail instanceof \Imagick);

        switch ($mimeType) {
            case 'image/png':
                $thumbnail->setImageFormat('png');

                break;
            case 'image/gif':
                $thumbnail->setImageFormat('gif');

                break;
            case 'image/jpg':
            case 'image/jpeg':
                $thumbnail->setImageFormat('jpeg');
                $thumbnail->setImageCompressionQuality($quality);

                break;
            case 'image/webp':
                if (!\in_array('WEBP', \Imagick::queryFormats('WEBP'), true)) {
                    throw MediaException::cannotCreateImage();
                }

                $thumbnail->setImageFormat('webp');
                $thumbnail->setImageCompressionQuality($quality);

                break;
            case 'image/avif':
                if (!\in_array('AVIF', \Imagick::queryFormats('AVIF'), true)) {
                    throw MediaException::cannotCreateImage();
                }

                $thumbnail->setImageFormat('avif');
                $thumbnail->setImageCompressionQuality($quality);

                break;
        }

        return $thumbnail->getImageBlob();
    }
}
