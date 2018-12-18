<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
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
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

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
     * @var EntityRepository
     */
    private $mediaFolderRepository;

    public function __construct(
        RepositoryInterface $mediaRepository,
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
     * @throws FileTypeNotSupportedException
     * @throws ThumbnailCouldNotBeSavedException
     */
    public function updateThumbnailsAfterUpload(MediaEntity $media, Context $context): void
    {
        $this->generateThumbnails($media, $context);
    }

    /**
     * @throws FileNotFoundException
     * @throws FileTypeNotSupportedException
     * @throws ThumbnailCouldNotBeSavedException
     */
    public function generateThumbnails(MediaEntity $media, Context $context): void
    {
        if (!$media->hasFile()) {
            return;
        }

        if (!$this->thumbnailsAreGeneratable($media)) {
            throw new FileTypeNotSupportedException($media->getId());
        }

        $config = $this->getConfigForMedia($media, $context);

        if ($config === null || $config->getCreateThumbnails() === false) {
            return;
        }

        $mediaImage = $this->getImageResource($media);
        $originalImageSize = $this->getOriginalImageSize($mediaImage);

        $savedThumbnails = [];
        try {
            foreach ($config->getMediaThumbnailSizes() as $size) {
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
        }
    }

    public function deleteThumbnails(MediaEntity $media, Context $context): void
    {
        $this->thumbnailRepository->deleteCascadingFromMedia($media, $context);
    }

    private function getConfigForMedia(MediaEntity $media, Context $context): ?MediaFolderConfigurationEntity
    {
        if (!$media->getMediaFolderId()) {
            return null;
        }

        $criteria = new ReadCriteria([$media->getMediaFolderId()]);
        $thumbnailSizesCriteria = new Criteria();
        $thumbnailSizesCriteria->addAssociation('mediaThumbnailSizes', new Criteria());
        $criteria->addAssociation('configuration', $thumbnailSizesCriteria);

        /** @var MediaFolderEntity $folder */
        $folder = $this->mediaFolderRepository->read($criteria, $context)->get($media->getMediaFolderId());

        return $folder->getConfiguration();
    }

    /**
     * @throws FileNotFoundException
     * @throws FileTypeNotSupportedException
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
