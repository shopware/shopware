<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail\Processor;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Thumbnail\DTO\ThumbnailImage;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class GdImageThumbnailProcessor implements ThumbnailProcessorInterface
{
    public function createImageFromString(string $file): ThumbnailImage
    {
        $image = @imagecreatefromstring($file);

        if ($image === false) {
            throw MediaException::cannotCreateImage();
        }

        return new ThumbnailImage($image);
    }

    public function rotate(ThumbnailImage $image, float $angle): ThumbnailImage
    {
        \assert($image->image instanceof \GdImage);

        $rotated = imagerotate($image->image, $angle, 0);

        if ($rotated === false) {
            throw MediaException::cannotCreateImage();
        }

        return new ThumbnailImage($rotated);
    }

    public function getWidth(ThumbnailImage $image): int
    {
        \assert($image->image instanceof \GdImage);

        return imagesx($image->image);
    }

    public function getHeight(ThumbnailImage $image): int
    {
        \assert($image->image instanceof \GdImage);

        return imagesy($image->image);
    }

    public function createNewImage(ThumbnailImage $mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize): ThumbnailImage
    {
        \assert($mediaImage->image instanceof \GdImage);

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
            $mediaImage->image,
            0,
            0,
            0,
            0,
            $thumbnailSize['width'],
            $thumbnailSize['height'],
            $originalImageSize['width'],
            $originalImageSize['height']
        );

        return new ThumbnailImage($thumbnail);
    }

    public function convertImage(ThumbnailImage $thumbnail, string $mimeType, int $quality): string
    {
        \assert($thumbnail->image instanceof \GdImage);

        ob_start();
        switch ($mimeType) {
            case 'image/png':
                imagepng($thumbnail->image);

                break;
            case 'image/gif':
                imagegif($thumbnail->image);

                break;
            case 'image/jpg':
            case 'image/jpeg':
                imagejpeg($thumbnail->image, null, $quality);

                break;
            case 'image/webp':
                if (!\function_exists('imagewebp')) {
                    throw MediaException::cannotCreateImage();
                }

                imagewebp($thumbnail->image, null, $quality);

                break;
            case 'image/avif':
                if (!\function_exists('imageavif')) {
                    throw MediaException::cannotCreateImage();
                }

                imageavif($thumbnail->image, null, $quality);

                break;
        }
        $imageFile = ob_get_contents();
        ob_end_clean();

        return (string) $imageFile;
    }
}
