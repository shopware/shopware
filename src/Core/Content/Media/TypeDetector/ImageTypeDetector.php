<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\TypeDetector;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;

class ImageTypeDetector implements TypeDetectorInterface
{
    protected const SUPPORTED_FILE_EXTENSIONS = [
        'jpg' => [],
        'jpeg' => [],
        'png' => [ImageType::TRANSPARENT],
        'webp' => [ImageType::TRANSPARENT],
        'gif' => [ImageType::TRANSPARENT],
        'svg' => [ImageType::VECTOR_GRAPHIC],
        'bmp' => [ImageType::TRANSPARENT],
        'tiff' => [ImageType::TRANSPARENT],
        'tif' => [ImageType::TRANSPARENT],
        'eps' => [ImageType::VECTOR_GRAPHIC],
    ];

    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType): ?MediaType
    {
        $fileExtension = mb_strtolower($mediaFile->getFileExtension());
        if (!array_key_exists($fileExtension, self::SUPPORTED_FILE_EXTENSIONS)) {
            return $previouslyDetectedType;
        }

        if ($previouslyDetectedType === null) {
            $previouslyDetectedType = new ImageType();
        }

        $previouslyDetectedType->addFlags(self::SUPPORTED_FILE_EXTENSIONS[$fileExtension]);
        $this->addAnimatedFlag($mediaFile, $previouslyDetectedType);

        return $previouslyDetectedType;
    }

    private function addAnimatedFlag(MediaFile $mediaFile, MediaType $rootType): void
    {
        $fileExtension = mb_strtolower($mediaFile->getFileExtension());
        if ($fileExtension === 'gif' && $this->isGifAnimated($mediaFile->getFileName())) {
            $rootType->addFlag(ImageType::ANIMATED);
        }

        if ($fileExtension === 'webp' && $this->isWebpAnimated($mediaFile->getFileName())) {
            $rootType->addFlag(ImageType::ANIMATED);
        }
    }

    /**
     * an animated gif contains multiple "frames", with each frame having a
     * header made up of:
     * * a static 4-byte sequence (\x00\x21\xF9\x04)
     * * 4 variable bytes
     * * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21)

     * We read through the file till we reach the end of the file, or we've found
     * at least 2 frame headers
     */
    private function isGifAnimated(string $filename): bool
    {
        if (!($fh = @fopen($filename, 'rb'))) {
            return false;
        }
        $count = 0;

        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
        }

        fclose($fh);

        return $count > 1;
    }

    /**
     * an animated WebP has an Animation Flag set in the Headers
     * (https://developers.google.com/speed/webp/docs/riff_container#extended_file_format)
     *
     * We check if the file uses the extended file format, which is necessary for animated images
     * then we check if the Animation Flag is set
     */
    private function isWebpAnimated(string $filename): bool
    {
        $result = false;
        $fh = fopen($filename, 'rb');
        fread($fh, 12);
        if (fread($fh, 4) === 'VP8X') {
            $animationByte = fread($fh, 1);
            $result = (ord($animationByte) >> 1) & 1 ? true : false;
        }
        fclose($fh);

        return $result;
    }
}
