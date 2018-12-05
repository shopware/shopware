<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaThumbnailRepository;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\ThumbnailCouldNotBeSavedException;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;

class ThumbnailService
{
    /**
     * @var RepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var MediaThumbnailRepository
     */
    private $thumbnailRepository;

    /**
     * @var FilesystemInterface
     */
    private $fileSystem;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ThumbnailConfiguration
     */
    private $configuration;

    public function __construct(
        RepositoryInterface $mediaRepository,
        MediaThumbnailRepository $thumbnailRepository,
        FilesystemInterface $fileSystem,
        UrlGeneratorInterface $urlGenerator,
        ThumbnailConfiguration $configuration
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->thumbnailRepository = $thumbnailRepository;
        $this->fileSystem = $fileSystem;
        $this->urlGenerator = $urlGenerator;
        $this->configuration = $configuration;
    }

    public function updateThumbnailsAfterUpload(MediaStruct $media, Context $context): void
    {
        if (!$this->configuration->isAutoGenerateAfterUpload()) {
            return;
        }

        $this->generateThumbnails($media, $context);
    }

    /**
     * @throws FileNotFoundException
     * @throws FileTypeNotSupportedException
     * @throws ThumbnailCouldNotBeSavedException
     */
    public function generateThumbnails(MediaStruct $media, Context $context): void
    {
        if (!$media->hasFile()) {
            return;
        }

        if (!$this->thumbnailsAreGeneratable($media)) {
            throw new FileTypeNotSupportedException($media->getId());
        }

        $mediaImage = $this->getImageResource($media);
        $originalImageSize = $this->getOriginalImageSize($mediaImage);

        $savedThumbnails = [];
        try {
            foreach ($this->configuration->getThumbnailSizes() as $size) {
                $thumbnailSize = $this->calculateThumbnailSize($originalImageSize, $size);
                $thumbnail = $this->createNewImage(
                    $mediaImage,
                    $media->getMediaType(),
                    $originalImageSize,
                    $thumbnailSize
                );

                $savedThumbnails[] = $this->saveThumbnail($media, $size, $thumbnail, false);

                if ($this->configuration->isHighDpi()) {
                    $savedThumbnails[] = $this->saveThumbnail($media, $size, $thumbnail, true);
                }

                imagedestroy($thumbnail);
            }
            imagedestroy($mediaImage);
        } finally {
            $this->persistThumbnailData($media, $savedThumbnails, $context);
        }
    }

    public function deleteThumbnails(MediaStruct $media, Context $context): void
    {
        $this->thumbnailRepository->deleteCascadingFromMedia($media, $context);
    }

    /**
     * @throws FileNotFoundException
     * @throws FileTypeNotSupportedException
     *
     * @return resource
     */
    private function getImageResource(MediaStruct $media)
    {
        $filePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $file = $this->fileSystem->read($filePath);
        $image = @imagecreatefromstring($file);
        if (!$image) {
            throw new FileTypeNotSupportedException($media->getId());
        }

        return $image;
    }

    private function getOriginalImageSize($image): array
    {
        return [
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
    }

    private function calculateThumbnailSize(array $imageSize, array $preferredThumbnailSize): array
    {
        if (!$this->configuration->isKeepProportions()) {
            return $preferredThumbnailSize;
        }

        if ($imageSize['width'] >= $imageSize['height']) {
            $aspectRatio = $imageSize['height'] / $imageSize['width'];

            return [
                'width' => (int) $preferredThumbnailSize['width'],
                'height' => (int) ceil($preferredThumbnailSize['height'] * $aspectRatio),
            ];
        }
        $aspectRatio = $imageSize['width'] / $imageSize['height'];

        return [
                'width' => (int) ceil($preferredThumbnailSize['width'] * $aspectRatio),
                'height' => (int) $preferredThumbnailSize['height'],
            ];
    }

    /**
     * @return resource
     */
    private function createNewImage($mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize)
    {
        $thumbnail = imagecreatetruecolor($thumbnailSize['width'], $thumbnailSize['height']);

        if (!$type->is(ImageType::TRANSPARENT)) {
            $colorWhite = imagecolorallocate($thumbnail, 255, 255, 255);
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

    /**
     * @throws ThumbnailCouldNotBeSavedException
     *
     * @return array
     */
    private function saveThumbnail(MediaStruct $media, array $size, $thumbnail, bool $isHighDpi): array
    {
        $quality = $isHighDpi ?
            $this->configuration->getHighDpiQuality() : $this->configuration->getStandardQuality();
        $url = $this->urlGenerator->getRelativeThumbnailUrl(
            $media,
            $size['width'],
            $size['height'],
            $isHighDpi
        );
        $this->writeThumbnail($thumbnail, $media->getMimeType(), $url, $quality);

        return $this->createThumbnailData($size, $isHighDpi);
    }

    /**
     * @throws ThumbnailCouldNotBeSavedException
     */
    private function writeThumbnail($thumbnail, string $mimeType, string $url, int $quality): void
    {
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
        }
        $imageFile = ob_get_contents();
        ob_end_clean();

        if ($this->fileSystem->put($url, $imageFile) === false) {
            throw new ThumbnailCouldNotBeSavedException($url);
        }
    }

    private function createThumbnailData(array $size, bool $isHighDpi): array
    {
        return [
            'width' => $size['width'],
            'height' => $size['height'],
            'highDpi' => $isHighDpi,
        ];
    }

    private function persistThumbnailData(MediaStruct $media, array $savedThumbnails, Context $context): void
    {
        $mediaData = [
            'id' => $media->getId(),
            'thumbnails' => $savedThumbnails,
        ];

        $wereThumbnailsWritable = $context->getWriteProtection()->isAllowed(MediaProtectionFlags::WRITE_THUMBNAILS);
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_THUMBNAILS);

        $this->mediaRepository->update([$mediaData], $context);

        if (!$wereThumbnailsWritable) {
            $context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_THUMBNAILS);
        }
    }

    private function thumbnailsAreGeneratable(MediaStruct $media): bool
    {
        if ($media->getMediaType() instanceof ImageType &&
            !$media->getMediaType()->is(ImageType::VECTOR_GRAPHIC) &&
            !$media->getMediaType()->is(ImageType::ANIMATED)
        ) {
            return true;
        }

        return false;
    }
}
