<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Event\MediaFileUploadedEvent;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\ThumbnailCouldNotBeSavedException;
use Shopware\Core\Content\Media\Util\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\Struct\StructCollection;
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
        $this->generateThumbnails($event->getMediaId(), $event->getMimeType(), $event->getContext());
    }

    /**
     * @throws FileNotFoundException
     * @throws FileTypeNotSupportedException
     * @throws ThumbnailCouldNotBeSavedException
     */
    public function generateThumbnails(string $mediaId, string $mimeType, Context $context): void
    {
        $mediaImage = $this->getImageResource($mediaId, $mimeType);
        $originalImageSize = $this->getOriginalImageSize($mediaImage);

        $savedThumbnails = new StructCollection();
        try {
            foreach ($this->configuration->getThumbnailSizes() as $size) {
                $thumbnailSize = $this->calculateThumbnailSize($originalImageSize, $size);
                $thumbnail = $this->createNewImage($mediaImage, $mimeType, $originalImageSize, $thumbnailSize);

                $url = $this->urlGenerator->getThumbnailUrl($mediaId, $mimeType, $size['width'], $size['height'], false, false);
                $this->saveThumbnail($thumbnail, $mimeType, $url, $this->configuration->getStandardQuality());
                $this->addThumbnailStruct($savedThumbnails, $size, false);

                if ($this->configuration->isHighDpi()) {
                    $url = $this->urlGenerator->getThumbnailUrl($mediaId, $mimeType, $size['width'], $size['height'], true, false);
                    $this->saveThumbnail($thumbnail, $mimeType, $url, $this->configuration->getHighDpiQuality());
                    $this->addThumbnailStruct($savedThumbnails, $size, true);
                }

                imagedestroy($thumbnail);
            }
            imagedestroy($mediaImage);
        } finally {
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

    /**
     * @throws FileNotFoundException
     * @throws FileTypeNotSupportedException
     *
     * @return resource
     */
    private function getImageResource(string $mediaId, string $mimeType)
    {
        $filePath = $this->urlGenerator->getMediaUrl($mediaId, $mimeType, false);
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
     * @param resource $thumbnail
     * @param string   $mimeType
     * @param string   $url
     * @param int      $quality
     *
     * @throws ThumbnailCouldNotBeSavedException
     */
    private function saveThumbnail($thumbnail, string $mimeType, string $url, int $quality): void
    {
        ob_start();
        switch ($mimeType) {
        case 'image/png':
            imagepng($thumbnail);
            break;
        case 'image/gif':
            imagegif($thumbnail);
            break;
        default:
            imagejpeg($thumbnail, null, $quality);
            break;
        }
        $imageFile = ob_get_contents();
        ob_end_clean();

        if ($this->fileSystem->put($url, $imageFile) === false) {
            throw new ThumbnailCouldNotBeSavedException($url);
        }
    }

    private function addThumbnailStruct(StructCollection $collection, array $size, bool $isHighDpi): void
    {
        $thumbnailStruct = new ThumbnailStruct();
        $thumbnailStruct->setWidth($size['width']);
        $thumbnailStruct->setHeight($size['height']);
        $thumbnailStruct->setHighDpi($isHighDpi);

        $collection->add($thumbnailStruct);
    }
}
