<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\ThumbnailCouldNotBeSavedException;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ThumbnailService
{
    private EntityRepositoryInterface $thumbnailRepository;

    private FilesystemInterface $filesystemPublic;

    private FilesystemInterface $filesystemPrivate;

    private UrlGeneratorInterface $urlGenerator;

    private EntityRepositoryInterface $mediaFolderRepository;

    public function __construct(
        EntityRepositoryInterface $thumbnailRepository,
        FilesystemInterface $fileSystemPublic,
        FilesystemInterface $fileSystemPrivate,
        UrlGeneratorInterface $urlGenerator,
        EntityRepositoryInterface $mediaFolderRepository
    ) {
        $this->thumbnailRepository = $thumbnailRepository;
        $this->filesystemPublic = $fileSystemPublic;
        $this->filesystemPrivate = $fileSystemPrivate;
        $this->urlGenerator = $urlGenerator;
        $this->mediaFolderRepository = $mediaFolderRepository;
    }

    public function generate(MediaCollection $collection, Context $context): int
    {
        $delete = [];

        $generate = [];

        foreach ($collection as $media) {
            if ($media->getThumbnails() === null) {
                throw new \RuntimeException('Thumbnail association not loaded - please pre load media thumbnails');
            }

            if (!$this->mediaCanHaveThumbnails($media, $context)) {
                $delete = array_merge($delete, $media->getThumbnails()->getIds());

                continue;
            }

            $mediaFolder = $media->getMediaFolder();
            if ($mediaFolder === null) {
                continue;
            }

            $config = $mediaFolder->getConfiguration();
            if ($config === null) {
                continue;
            }

            $delete = array_merge($delete, $media->getThumbnails()->getIds());

            $generate[] = $media;
        }

        $updates = [];
        foreach ($generate as $media) {
            if ($media->getMediaFolder() === null || $media->getMediaFolder()->getConfiguration() === null) {
                continue;
            }

            $config = $media->getMediaFolder()->getConfiguration();

            $thumbnails = $this->createThumbnailsForSizes($media, $config, $config->getMediaThumbnailSizes());

            foreach ($thumbnails as $thumbnail) {
                $updates[] = $thumbnail;
            }
        }

        $updates = array_values(array_filter($updates));

        if (!empty($delete)) {
            $this->thumbnailRepository->delete($delete, $context);
        }

        if (empty($updates)) {
            return 0;
        }

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($updates): void {
            $this->thumbnailRepository->create($updates, $context);
        });

        return \count($updates);
    }

    /**
     * @deprecated tag:v6.5.0 - Use `generate` instead
     *
     * @throws FileTypeNotSupportedException
     * @throws ThumbnailCouldNotBeSavedException
     */
    public function generateThumbnails(MediaEntity $media, Context $context): int
    {
        if (!$this->mediaCanHaveThumbnails($media, $context)) {
            $this->deleteAssociatedThumbnails($media, $context);

            return 0;
        }

        $mediaFolder = $media->getMediaFolder();
        if ($mediaFolder === null) {
            return 0;
        }

        $config = $mediaFolder->getConfiguration();
        if ($config === null) {
            return 0;
        }

        /** @var MediaThumbnailCollection $toBeDeletedThumbnails */
        $toBeDeletedThumbnails = $media->getThumbnails();
        $this->thumbnailRepository->delete($toBeDeletedThumbnails->getIds(), $context);

        $update = $this->createThumbnailsForSizes($media, $config, $config->getMediaThumbnailSizes());

        if (empty($update)) {
            return 0;
        }

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($update): void {
            $this->thumbnailRepository->create($update, $context);
        });

        return \count($update);
    }

    /**
     * @throws FileTypeNotSupportedException
     * @throws ThumbnailCouldNotBeSavedException
     */
    public function updateThumbnails(MediaEntity $media, Context $context): int
    {
        if (!$this->mediaCanHaveThumbnails($media, $context)) {
            $this->deleteAssociatedThumbnails($media, $context);

            return 0;
        }

        $mediaFolder = $media->getMediaFolder();
        if ($mediaFolder === null) {
            return 0;
        }

        $config = $mediaFolder->getConfiguration();
        if ($config === null) {
            return 0;
        }

        $tobBeCreatedSizes = new MediaThumbnailSizeCollection($config->getMediaThumbnailSizes()->getElements());
        $toBeDeletedThumbnails = new MediaThumbnailCollection($media->getThumbnails()->getElements());

        foreach ($tobBeCreatedSizes as $thumbnailSize) {
            foreach ($toBeDeletedThumbnails as $thumbnail) {
                if ($thumbnail->getWidth() === $thumbnailSize->getWidth()
                    && $thumbnail->getHeight() === $thumbnailSize->getHeight()
                ) {
                    $toBeDeletedThumbnails->remove($thumbnail->getId());
                    $tobBeCreatedSizes->remove($thumbnailSize->getId());

                    continue 2;
                }
            }
        }

        $this->thumbnailRepository->delete($toBeDeletedThumbnails->getIds(), $context);

        $update = $this->createThumbnailsForSizes($media, $config, $tobBeCreatedSizes);

        if (empty($update)) {
            return 0;
        }

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($update): void {
            $this->thumbnailRepository->create($update, $context);
        });

        return \count($update);
    }

    public function deleteThumbnails(MediaEntity $media, Context $context): void
    {
        $this->deleteAssociatedThumbnails($media, $context);
    }

    /**
     * @throws FileTypeNotSupportedException
     * @throws ThumbnailCouldNotBeSavedException
     */
    private function createThumbnailsForSizes(
        MediaEntity $media,
        MediaFolderConfigurationEntity $config,
        ?MediaThumbnailSizeCollection $thumbnailSizes
    ): array {
        if ($thumbnailSizes === null || $thumbnailSizes->count() === 0) {
            return [];
        }

        $mediaImage = $this->getImageResource($media);
        $originalImageSize = $this->getOriginalImageSize($mediaImage);
        $originalUrl = $this->urlGenerator->getRelativeMediaUrl($media);

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
                    (new MediaThumbnailEntity())->assign(['width' => $size->getWidth(), 'height' => $size->getHeight()])
                );
                $this->writeThumbnail($thumbnail, $media, $url, $config->getThumbnailQuality());

                $mediaFilesystem = $this->getFileSystem($media);
                if ($originalImageSize === $thumbnailSize
                    && $mediaFilesystem->getSize($originalUrl) < $mediaFilesystem->getSize($url)) {
                    $mediaFilesystem->update($url, $mediaFilesystem->read($originalUrl));
                }

                $savedThumbnails[] = [
                    'mediaId' => $media->getId(),
                    'width' => $size->getWidth(),
                    'height' => $size->getHeight(),
                ];

                imagedestroy($thumbnail);
            }
            imagedestroy($mediaImage);
        } finally {
            return $savedThumbnails;
        }
    }

    private function ensureConfigIsLoaded(MediaEntity $media, Context $context): void
    {
        $mediaFolderId = $media->getMediaFolderId();
        if ($mediaFolderId === null) {
            return;
        }

        if ($media->getMediaFolder() !== null) {
            return;
        }

        $criteria = new Criteria([$mediaFolderId]);
        $criteria->addAssociation('configuration.mediaThumbnailSizes');

        /** @var MediaFolderEntity $folder */
        $folder = $this->mediaFolderRepository->search($criteria, $context)->get($mediaFolderId);
        $media->setMediaFolder($folder);
    }

    /**
     * @throws FileTypeNotSupportedException
     *
     * @return resource
     */
    private function getImageResource(MediaEntity $media)
    {
        $filePath = $this->urlGenerator->getRelativeMediaUrl($media);
        /** @var string $file */
        $file = $this->getFileSystem($media)->read($filePath);
        $image = @imagecreatefromstring($file);
        if ($image === false) {
            throw new FileTypeNotSupportedException($media->getId());
        }

        if (\function_exists('exif_read_data')) {
            /** @var resource $stream */
            $stream = fopen('php://memory', 'r+b');

            try {
                // use in-memory stream to read the EXIF-metadata,
                // to avoid downloading the image twice from a remote filesystem
                fwrite($stream, $file);
                rewind($stream);

                $exif = @exif_read_data($stream);

                if ($exif !== false) {
                    if (!empty($exif['Orientation']) && $exif['Orientation'] === 8) {
                        $image = imagerotate($image, 90, 0);
                    } elseif (!empty($exif['Orientation']) && $exif['Orientation'] === 3) {
                        $image = imagerotate($image, 180, 0);
                    } elseif (!empty($exif['Orientation']) && $exif['Orientation'] === 6) {
                        $image = imagerotate($image, -90, 0);
                    }
                }
            } catch (\Exception $e) {
                // Ignore.
            } finally {
                fclose($stream);
            }
        }

        if ($image === false) {
            throw new FileTypeNotSupportedException($media->getId());
        }

        return $image;
    }

    /**
     * @param resource $image
     */
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
        if (!$config->getKeepAspectRatio() || $preferredThumbnailSize->getWidth() !== $preferredThumbnailSize->getHeight()) {
            $calculatedWidth = $preferredThumbnailSize->getWidth();
            $calculatedHeight = $preferredThumbnailSize->getHeight();

            $useOriginalSizeInThumbnails = $imageSize['width'] < $calculatedWidth || $imageSize['height'] < $calculatedHeight;

            return $useOriginalSizeInThumbnails ? [
                'width' => $imageSize['width'],
                'height' => $imageSize['height'],
            ] : [
                'width' => $calculatedWidth,
                'height' => $calculatedHeight,
            ];
        }

        if ($imageSize['width'] >= $imageSize['height']) {
            $aspectRatio = $imageSize['height'] / $imageSize['width'];

            $calculatedWidth = $preferredThumbnailSize->getWidth();
            $calculatedHeight = (int) ceil($preferredThumbnailSize->getHeight() * $aspectRatio);

            $useOriginalSizeInThumbnails = $imageSize['width'] < $calculatedWidth || $imageSize['height'] < $calculatedHeight;

            return $useOriginalSizeInThumbnails ? [
                'width' => $imageSize['width'],
                'height' => $imageSize['height'],
            ] : [
                'width' => $calculatedWidth,
                'height' => $calculatedHeight,
            ];
        }

        $aspectRatio = $imageSize['width'] / $imageSize['height'];

        $calculatedWidth = (int) ceil($preferredThumbnailSize->getWidth() * $aspectRatio);
        $calculatedHeight = $preferredThumbnailSize->getHeight();

        $useOriginalSizeInThumbnails = $imageSize['width'] < $calculatedWidth || $imageSize['height'] < $calculatedHeight;

        return $useOriginalSizeInThumbnails ? [
            'width' => $imageSize['width'],
            'height' => $imageSize['height'],
        ] : [
            'width' => $calculatedWidth,
            'height' => $calculatedHeight,
        ];
    }

    /**
     * @param resource $mediaImage
     *
     * @return resource
     */
    private function createNewImage($mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize)
    {
        $thumbnail = imagecreatetruecolor($thumbnailSize['width'], $thumbnailSize['height']);

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

    /**
     * @param resource $thumbnail
     *
     * @throws ThumbnailCouldNotBeSavedException
     */
    private function writeThumbnail($thumbnail, MediaEntity $media, string $url, int $quality): void
    {
        ob_start();
        switch ($media->getMimeType()) {
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

        if ($this->getFileSystem($media)->put($url, $imageFile) === false) {
            throw new ThumbnailCouldNotBeSavedException($url);
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
        return $media->getMediaType() instanceof ImageType
            && !$media->getMediaType()->is(ImageType::VECTOR_GRAPHIC)
            && !$media->getMediaType()->is(ImageType::ANIMATED)
            && !$media->getMediaType()->is(ImageType::ICON);
    }

    private function deleteAssociatedThumbnails(MediaEntity $media, Context $context): void
    {
        $associatedThumbnails = $media->getThumbnails()->getIds();
        $this->thumbnailRepository->delete($associatedThumbnails, $context);
    }

    private function getFileSystem(MediaEntity $media): FilesystemInterface
    {
        if ($media->isPrivate()) {
            return $this->filesystemPrivate;
        }

        return $this->filesystemPublic;
    }
}
