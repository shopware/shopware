<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail\Processor;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class GdImageThumbnailProcessor implements ThumbnailProcessorInterface
{
    public function createImageFromString(string $file): \GdImage
    {
        $image = @imagecreatefromstring($file);

        if ($image === false) {
            throw MediaException::cannotCreateImage();
        }

        return $image;
    }

    public function rotate(object $image, float $angle): \GdImage
    {
        \assert($image instanceof \GdImage);

        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            throw MediaException::cannotCreateImage();
        }

        return $rotated;
    }

    public function getWidth(object $image): int
    {
        \assert($image instanceof \GdImage);

        return imagesx($image);
    }

    public function getHeight(object $image): int
    {
        \assert($image instanceof \GdImage);

        return imagesy($image);
    }

    public function createNewImage(object $mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize): \GdImage
    {
        \assert($mediaImage instanceof \GdImage);

        $thumbnail = imagecreatetruecolor($thumbnailSize['width'], $thumbnailSize['height']);

        if ($thumbnail === false) {
            throw MediaException::cannotCreateImage();
        }

        if (!$type->is(ImageType::TRANSPARENT)) {
            $colorWhite = (int) imagecolorallocate($thumbnail, 255, 255, 255);
            imagefill($thumbnail, 0, 0, $colorWhite);
        } else {
            imagealphablending($thumbnail, false);
        }

        imagesavealpha($thumbnail, true);
        imagecopyresampled(
            $thumbnail,
            $mediaImage,
            0,
            0,
            0,
            0,
            $thumbnailSize['width'],
            $thumbnailSize['height'],
            $originalImageSize['width'],
            $originalImageSize['height']
        );

        return $thumbnail;
    }

    public function convertImage(object $thumbnail, string $mimeType, int $quality): string
    {
        \assert($thumbnail instanceof \GdImage);

        ob_start();
        switch ($mimeType) {
            case 'image/png':
                imagepng($thumbnail);

                break;
            case 'image/gif':
                imagegif($thumbnail);

                break;
            case 'image/jpg':
            case 'image/jpeg':
                imagejpeg($thumbnail, null, $quality);

                break;
            case 'image/webp':
                if (!\function_exists('imagewebp')) {
                    throw MediaException::cannotCreateImage();
                }

                imagewebp($thumbnail, null, $quality);

                break;
            case 'image/avif':
                if (!\function_exists('imageavif')) {
                    throw MediaException::cannotCreateImage();
                }

                imageavif($thumbnail, null, $quality);

                break;
        }
        $imageFile = ob_get_contents();
        ob_end_clean();

        return (string) $imageFile;
    }
}
