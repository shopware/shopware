<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Event\MediaFileUploadedEvent;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\ThumbnailCouldNotBeSavedException;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThumbnailService implements EventSubscriberInterface
{
    /**
     * @var EntityRepository
     */
    private $mediaRepository;

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
        EntityRepository $mediaRepository,
        FilesystemInterface $fileSystem,
        UrlGeneratorInterface $urlGenerator,
        ThumbnailConfiguration $configuration
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->fileSystem = $fileSystem;
        $this->urlGenerator = $urlGenerator;
        $this->configuration = $configuration;
    }

    public static function getSubscribedEvents()
    {
        return [
            MediaFileUploadedEvent::EVENT_NAME => 'updateMediaThumbnails',
        ];
    }

    public function updateMediaThumbnails(MediaFileUploadedEvent $event)
    {
        if (!$this->configuration->isAutoGenerateAfterUpload()) {
            return;
        }
        $this->generateThumbnails(
            $event->getMediaId(),
            $event->getMimeType(),
            $event->getFileExtension(),
            $event->getContext()
        );
    }

    /**
     * @throws FileNotFoundException
     * @throws FileTypeNotSupportedException
     * @throws ThumbnailCouldNotBeSavedException
     */
    public function generateThumbnails(string $mediaId, string $mimeType, string $extension, Context $context): void
    {
        $mediaImage = $this->getImageResource($mediaId, $extension);
        $originalImageSize = $this->getOriginalImageSize($mediaImage);

        $savedThumbnails = [];
        try {
            foreach ($this->configuration->getThumbnailSizes() as $size) {
                $thumbnailSize = $this->calculateThumbnailSize($originalImageSize, $size);
                $thumbnail = $this->createNewImage($mediaImage, $mimeType, $originalImageSize, $thumbnailSize);

                $savedThumbnails[] = $this->saveThumbnail($mediaId, $mimeType, $extension, $size, $thumbnail, false);

                if ($this->configuration->isHighDpi()) {
                    $savedThumbnails[] = $this->saveThumbnail($mediaId, $mimeType, $extension, $size, $thumbnail, true);
                }

                imagedestroy($thumbnail);
            }
            imagedestroy($mediaImage);
        } finally {
            $this->persistThumbnailData($mediaId, $savedThumbnails, $context);
        }
    }

    /**
     * @throws FileNotFoundException
     * @throws FileTypeNotSupportedException
     *
     * @return resource
     */
    private function getImageResource(string $mediaId, string $extension)
    {
        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaId, $extension);
        $file = $this->fileSystem->read($filePath);
        $image = @imagecreatefromstring($file);
        if (!$image) {
            throw new FileTypeNotSupportedException($mediaId);
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
    private function createNewImage($mediaImage, string $mimeType, array $originalImageSize, array $thumbnailSize)
    {
        $thumbnail = imagecreatetruecolor($thumbnailSize['width'], $thumbnailSize['height']);

        if ($mimeType === 'image/jpeg') {
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
    private function saveThumbnail(
        string $mediaId,
        string $mimeType,
        string $extension,
        array $size,
        $thumbnail,
        bool $isHighDpi
    ): array {
        $quality = $isHighDpi ?
            $this->configuration->getHighDpiQuality() : $this->configuration->getStandardQuality();
        $url = $this->urlGenerator->getRelativeThumbnailUrl($mediaId, $extension, $size['width'], $size['height'], $isHighDpi);
        $this->writeThumbnail($thumbnail, $mimeType, $url, $quality);

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

    private function persistThumbnailData(string $mediaId, array $savedThumbnails, Context $context): void
    {
        $mediaData = [
            'id' => $mediaId,
            'thumbnails' => $savedThumbnails,
        ];

        $writeProtection = $context->getExtension('write_protection');
        $wereThumbnailsWritable = $writeProtection->getExtension('write_thumbnails');
        $writeProtection->set('write_thumbnails', true);

        $this->mediaRepository->update([$mediaData], $context);
        $writeProtection->set('write_thumbnails', $wereThumbnailsWritable);
    }
}
