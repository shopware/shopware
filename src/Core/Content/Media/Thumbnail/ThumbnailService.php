<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaThumbnailRepository;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\ThumbnailCouldNotBeSavedException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;

class ThumbnailService
{
    /**
     * @var EntityRepositoryInterface
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
     * @var EntityRepository
     */
    private $mediaFolderRepository;

    public function __construct(
        EntityRepositoryInterface $mediaRepository,
        MediaThumbnailRepository $thumbnailRepository,
        FilesystemInterface $fileSystem,
        UrlGeneratorInterface $urlGenerator,
        EntityRepository $mediaFolderRepository
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->thumbnailRepository = $thumbnailRepository;
        $this->fileSystem = $fileSystem;
        $this->urlGenerator = $urlGenerator;
        $this->mediaFolderRepository = $mediaFolderRepository;
    }

    /**
     * @throws FileNotFoundException
     * @throws ThumbnailCouldNotBeSavedException
     */
    public function updateThumbnailsAfterUpload(MediaEntity $media, Context $context): int
    {
        if (!$this->mediaCanHaveThumbnails($media, $context)) {
            return 0;
        }

        $config = $media->getMediaFolder()->getConfiguration();

        return $this->createThumbnailsForSizes($media, $config, $config->getMediaThumbnailSizes(), $context);
    }

    public function updateThumbnails(MediaEntity $media, Context $context): int
    {
        if (!$this->mediaCanHaveThumbnails($media, $context)) {
            $this->thumbnailRepository->deleteCascadingFromMedia($media, $context);

            return 0;
        }

        $config = $media->getMediaFolder()->getConfiguration();

        $tobBeCreatedSizes = new MediaThumbnailSizeCollection($config->getMediaThumbnailSizes()->getElements());
        $toBeDeletedThumbnails = new MediaThumbnailCollection($media->getThumbnails()->getElements());

        foreach ($tobBeCreatedSizes as $thumbnailSize) {
            foreach ($toBeDeletedThumbnails as $thumbnail) {
                if ($thumbnail->getWidth() === $thumbnailSize->getWidth() &&
                    $thumbnail->getHeight() === $thumbnailSize->getHeight()
                ) {
                    $toBeDeletedThumbnails->remove($thumbnail->getId());
                    $tobBeCreatedSizes->remove($thumbnailSize->getId());
                    continue 2;
                }
            }
        }

        $this->thumbnailRepository->delete($toBeDeletedThumbnails->getIds(), $context);

        return $this->createThumbnailsForSizes($media, $config, $tobBeCreatedSizes, $context);
    }

    public function deleteThumbnails(MediaEntity $media, Context $context): void
    {
        $this->thumbnailRepository->deleteCascadingFromMedia($media, $context);
    }

    private function createThumbnailsForSizes(
        MediaEntity $media,
        MediaFolderConfigurationEntity $config,
        MediaThumbnailSizeCollection $thumbnailSizes,
        Context $context
    ): int {
        if ($thumbnailSizes->count() === 0) {
            return 0;
        }

        $mediaImage = $this->getImageResource($media);
        $originalImageSize = $this->getOriginalImageSize($mediaImage);

        $savedThumbnails = [];
        try {
            foreach ($thumbnailSizes as $size) {
                $thumbnailSize = $this->calculateThumbnailSize($originalImageSize, $size, $config);
                $thumbnail = $this->createNewImage(
                    $mediaImage,
                    $media->getMediaType(),
                    $originalImageSize,
                    $thumbnailSize
                );

                $url = $this->urlGenerator->getRelativeThumbnailUrl(
                    $media,
                    $size->getWidth(),
                    $size->getHeight()
                );
                $this->writeThumbnail($thumbnail, $media->getMimeType(), $url, $config->getThumbnailQuality());
                $savedThumbnails[] = [
                    'width' => $size->getWidth(),
                    'height' => $size->getHeight(),
                ];

                imagedestroy($thumbnail);
            }
            imagedestroy($mediaImage);
        } finally {
            $this->persistThumbnailData($media, $savedThumbnails, $context);

            return count($savedThumbnails);
        }
    }

    private function ensureConfigIsLoaded(MediaEntity $media, Context $context): void
    {
        if (!$media->getMediaFolderId()) {
            return;
        }

        if ($media->getMediaFolder() !== null) {
            return;
        }

        $criteria = new ReadCriteria([$media->getMediaFolderId()]);
        /** @var MediaFolderEntity $folder */
        $folder = $this->mediaFolderRepository->read($criteria, $context)->get($media->getMediaFolderId());
        $media->setMediaFolder($folder);
    }

    /**
     * @throws FileNotFoundException
     *
     * @return resource
     */
    private function getImageResource(MediaEntity $media)
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

    private function calculateThumbnailSize(
        array $imageSize,
        MediaThumbnailSizeEntity $preferredThumbnailSize,
        MediaFolderConfigurationEntity $config
    ): array {
        if (!$config->getKeepAspectRatio()) {
            return [
                'width' => $preferredThumbnailSize->getWidth(),
                'height' => $preferredThumbnailSize->getHeight(),
            ];
        }

        if ($imageSize['width'] >= $imageSize['height']) {
            $aspectRatio = $imageSize['height'] / $imageSize['width'];

            return [
                'width' => $preferredThumbnailSize->getWidth(),
                'height' => (int) ceil($preferredThumbnailSize->getHeight() * $aspectRatio),
            ];
        }

        $aspectRatio = $imageSize['width'] / $imageSize['height'];

        return [
            'width' => (int) ceil($preferredThumbnailSize->getWidth() * $aspectRatio),
            'height' => $preferredThumbnailSize->getHeight(),
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

    private function persistThumbnailData(MediaEntity $media, array $savedThumbnails, Context $context): void
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

    private function mediaCanHaveThumbnails(MediaEntity $media, Context $context): bool
    {
        if (!$media->hasFile()) {
            return false;
        }

        if (!$this->thumbnailsAreGeneratable($media)) {
            return false;
        }

        $this->ensureConfigIsLoaded($media, $context);

        if ($media->getMediaFolder() === null || $media->getMediaFolder()->getConfiguration() === null) {
            return false;
        }

        return $media->getMediaFolder()->getConfiguration()->getCreateThumbnails();
    }

    private function thumbnailsAreGeneratable(MediaEntity $media): bool
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
