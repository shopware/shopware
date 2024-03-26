<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\TypeDetector;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class ImageTypeDetector implements TypeDetectorInterface
{
    protected const SUPPORTED_FILE_EXTENSIONS = [
        'jpg' => [],
        'jpeg' => [],
        'png' => [ImageType::TRANSPARENT],
        'webp' => [ImageType::TRANSPARENT],
        'avif' => [ImageType::TRANSPARENT],
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
        if (!\array_key_exists($fileExtension, self::SUPPORTED_FILE_EXTENSIONS)) {
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

        if ($fileExtension === 'avif' && $this->isAvifAnimated($mediaFile->getFileName())) {
            $rootType->addFlag(ImageType::ANIMATED);
        }
    }

    /**
     * an animated gif contains multiple "frames", with each frame having a
     * header made up of:
     * * a static 4-byte sequence (\x00\x21\xF9\x04)
     * * 4 variable bytes
     * * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21)
     *
     * We read through the file till we reach the end of the file, or we've found
     * at least 2 frame headers
     */
    private function isGifAnimated(string $filename): bool
    {
        if (!($fh = @fopen($filename, 'r'))) {
            return false;
        }
        $count = 0;

        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); // read 100kb at a time
            if ($chunk === false) {
                throw MediaException::cannotOpenSourceStreamToRead($filename);
            }
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
        $fh = fopen($filename, 'r');
        if ($fh === false) {
            throw MediaException::cannotOpenSourceStreamToRead($filename);
        }
        fread($fh, 12);
        if (fread($fh, 4) === 'VP8X') {
            // extended flags are in the byte 21st
            fseek($fh, 20);
            $extendedFlags = fread($fh, 1);
            if ($extendedFlags === false) {
                throw MediaException::cannotOpenSourceStreamToRead($filename);
            }
            // move the bits of $extendedFlags one bit position to the right so that the animation bit flag is on the first position
            // [00101100] & [00000001] results to [00000000], [00101101] & [00000001] results to [00000001]
            $result = (bool) ((\ord($extendedFlags) >> 1) & 00000001);
        }
        fclose($fh);

        return $result;
    }

    /**
     * An animated avif has an animation type set in the header
     * non-animated or single-frame files would have the ftypavif header
     *
     * We check if the file contains an avis container definition (animated container definition)
     * non-animated or single-frame files do not have this
     */
    private function isAvifAnimated(string $filename): bool
    {
        $fh = @fopen($filename, 'r');

        if ($fh === false) {
            throw MediaException::cannotOpenSourceStreamToRead($filename);
        }

        $header = (string) fread($fh, 12);

        if (substr($header, 4, 8) !== 'ftypavis') {
            fclose($fh);

            return false;
        }

        while (!feof($fh)) {
            $containerDefinition = fread($fh, 4);

            if ($containerDefinition === 'avis') {
                fclose($fh);

                return true;
            }

            // the avis container definition must exist before the meta container definition to be valid, so we already return false here
            if ($containerDefinition === 'meta') {
                fclose($fh);

                return false;
            }
        }

        fclose($fh);

        return false;
    }
}
