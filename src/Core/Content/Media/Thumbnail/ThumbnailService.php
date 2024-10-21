<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\Core\Event\UpdateThumbnailPathEvent;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Shopware\Core\Content\Media\Event\MediaPathChangedEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Subscriber\MediaDeletionSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-type ImageSize array{width: int<1, max>, height: int<1, max>}
 */
#[Package('buyers-experience')]
class ThumbnailService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $thumbnailRepository,
        private readonly FilesystemOperator $filesystemPublic,
        private readonly FilesystemOperator $filesystemPrivate,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EntityIndexer $indexer,
        private readonly ThumbnailSizeCalculator $thumbnailSizeCalculator,
        private readonly Connection $connection,
        private readonly bool $remoteThumbnailsEnable = false
    ) {
    }

    public function generate(MediaCollection $collection, Context $context): int
    {
        if ($this->remoteThumbnailsEnable) {
            throw MediaException::thumbnailGenerationDisabled();
        }

        $delete = [];

        $generate = [];

        foreach ($collection as $media) {
            if ($media->getThumbnails() === null) {
                throw MediaException::thumbnailAssociationNotLoaded();
            }

            if (!$this->mediaCanHaveThumbnails($media, $context)) {
                $delete = [...$delete, ...$media->getThumbnails()->getIds()];

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

            $delete = [...$delete, ...$media->getThumbnails()->getIds()];

            $generate[] = $media;
        }

        // disable media indexing to trigger it once after processing all thumbnails
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        if (!empty($delete)) {
            $context->addState(MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);

            $delete = \array_values(\array_map(fn (string $id) => ['id' => $id], $delete));

            $this->thumbnailRepository->delete($delete, $context);
        }

        $updates = [];
        foreach ($generate as $media) {
            if ($media->getMediaFolder() === null || $media->getMediaFolder()->getConfiguration() === null) {
                continue;
            }

            $config = $media->getMediaFolder()->getConfiguration();

            $thumbnails = $this->generateAndSave($media, $config, $context, $config->getMediaThumbnailSizes());

            foreach ($thumbnails as $thumbnail) {
                $updates[] = $thumbnail;
            }
        }

        $this->indexer->handle(new MediaIndexingMessage($collection->getIds()));

        return \count($updates);
    }

    /**
     * @throws MediaException
     */
    public function updateThumbnails(MediaEntity $media, Context $context, bool $strict): int
    {
        if ($this->remoteThumbnailsEnable) {
            throw MediaException::thumbnailGenerationDisabled();
        }

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

        $strict = \func_get_args()[2] ?? false;

        if ($config->getMediaThumbnailSizes() === null) {
            return 0;
        }
        if ($media->getThumbnails() === null) {
            return 0;
        }

        $toBeCreatedSizes = new MediaThumbnailSizeCollection($config->getMediaThumbnailSizes()->getElements());
        $toBeDeletedThumbnails = new MediaThumbnailCollection($media->getThumbnails()->getElements());

        foreach ($toBeCreatedSizes as $thumbnailSize) {
            foreach ($toBeDeletedThumbnails as $thumbnail) {
                if (!$this->isSameDimension($thumbnail, $thumbnailSize)) {
                    continue;
                }

                if ($strict === true && !$this->getFileSystem($media)->fileExists($thumbnail->getPath())) {
                    continue;
                }

                $toBeDeletedThumbnails->remove($thumbnail->getId());
                $toBeCreatedSizes->remove($thumbnailSize->getId());

                continue 2;
            }
        }

        $delete = \array_values(\array_map(static fn (string $id) => ['id' => $id], $toBeDeletedThumbnails->getIds()));

        $update = $this->connection->transactional(function () use ($delete, $media, $config, $context, $toBeCreatedSizes): array {
            return $context->state(function () use ($delete, $media, $config, $context, $toBeCreatedSizes): array {
                $this->thumbnailRepository->delete($delete, $context);

                $updated = $this->generateAndSave($media, $config, $context, $toBeCreatedSizes);

                $this->indexer->handle(new MediaIndexingMessage([$media->getId()]));

                return $updated;
            }, EntityIndexerRegistry::DISABLE_INDEXING, MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);
        });

        return \count($update);
    }

    public function deleteThumbnails(MediaEntity $media, Context $context): void
    {
        if ($this->remoteThumbnailsEnable) {
            throw MediaException::thumbnailGenerationDisabled();
        }

        $this->deleteAssociatedThumbnails($media, $context);
    }

    /**
     * @return array<array{id:string, mediaId:string, width:int, height:int}>
     */
    private function generateAndSave(MediaEntity $media, MediaFolderConfigurationEntity $config, Context $context, ?MediaThumbnailSizeCollection $sizes): array
    {
        if ($sizes === null || $sizes->count() === 0) {
            return [];
        }

        $image = $this->getImageResource($media);

        $imageSize = $this->getOriginalImageSize($image);

        $records = [];

        $type = $media->getMediaType();
        if ($type === null) {
            throw MediaException::mediaTypeNotLoaded($media->getId());
        }

        $mapped = [];
        foreach ($sizes as $size) {
            $id = Uuid::randomHex();

            $mapped[$size->getId()] = $id;

            $records[] = [
                'id' => $id,
                'mediaId' => $media->getId(),
                'width' => $size->getWidth(),
                'height' => $size->getHeight(),
            ];
        }

        // write thumbnail records to trigger path generation afterward
        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($records): void {
            $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

            $this->thumbnailRepository->create($records, $context);
        });

        $ids = \array_column($records, 'id');

        // triggers the path generation for the persisted thumbnails
        $this->dispatcher->dispatch(new UpdateThumbnailPathEvent($ids));

        // create hash map for easy path access
        $paths = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), path FROM media_thumbnail WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        try {
            $event = new MediaPathChangedEvent($context);

            foreach ($sizes as $size) {
                $id = $mapped[$size->getId()];

                $thumbnailSize = $this->calculateThumbnailSize($imageSize, $size, $config);

                $thumbnail = $this->createNewImage($image, $type, $imageSize, $thumbnailSize);

                $path = $paths[$id];

                $this->writeThumbnail($thumbnail, $media, $path, $config->getThumbnailQuality());

                $fileSystem = $this->getFileSystem($media);
                if ($imageSize === $thumbnailSize && $fileSystem->fileSize($media->getPath()) < $fileSystem->fileSize($path)) {
                    // write file to file system
                    $fileSystem->write($path, $fileSystem->read($media->getPath()));
                }

                imagedestroy($thumbnail);

                $event->thumbnail(
                    mediaId: $media->getId(),
                    thumbnailId: $id,
                    path: $path,
                );
            }

            $this->dispatcher->dispatch($event);

            imagedestroy($image);
        } finally {
            return $records;
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

    private function getImageResource(MediaEntity $media): \GdImage
    {
        $filePath = $media->getPath();

        /** @var string $file */
        $file = $this->getFileSystem($media)->read($filePath);
        $image = @imagecreatefromstring($file);
        if ($image === false) {
            throw MediaException::thumbnailNotSupported($media->getId());
        }

        if (\function_exists('exif_read_data')) {
            /** @var resource $stream */
            $stream = fopen('php://memory', 'r+');

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
            } catch (\Exception) {
                // Ignore.
            } finally {
                fclose($stream);
            }
        }

        if ($image === false) {
            throw MediaException::thumbnailNotSupported($media->getId());
        }

        return $image;
    }

    /**
     * @return ImageSize
     */
    private function getOriginalImageSize(\GdImage $image): array
    {
        return [
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
    }

    /**
     * @param ImageSize $imageSize
     *
     * @return ImageSize
     */
    private function calculateThumbnailSize(
        array $imageSize,
        MediaThumbnailSizeEntity $preferredThumbnailSize,
        MediaFolderConfigurationEntity $config
    ): array {
        if (!$config->getKeepAspectRatio()) {
            return $this->thumbnailSizeCalculator->determineValidSize(
                $imageSize,
                $preferredThumbnailSize->getWidth(),
                $preferredThumbnailSize->getHeight()
            );
        }

        return $this->thumbnailSizeCalculator->calculate($imageSize, $preferredThumbnailSize);
    }

    /**
     * @param ImageSize $originalImageSize
     * @param ImageSize $thumbnailSize
     */
    private function createNewImage(\GdImage $mediaImage, MediaType $type, array $originalImageSize, array $thumbnailSize): \GdImage
    {
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

    private function writeThumbnail(\GdImage $thumbnail, MediaEntity $media, string $url, int $quality): void
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
            case 'image/webp':
                if (!\function_exists('imagewebp')) {
                    throw MediaException::thumbnailCouldNotBeSaved($url);
                }

                imagewebp($thumbnail, null, $quality);

                break;
            case 'image/avif':
                if (!\function_exists('imageavif')) {
                    throw MediaException::thumbnailCouldNotBeSaved($url);
                }

                imageavif($thumbnail, null, $quality);

                break;
        }
        $imageFile = ob_get_contents();
        ob_end_clean();

        try {
            $this->getFileSystem($media)->write($url, (string) $imageFile);
        } catch (\Exception) {
            throw MediaException::thumbnailCouldNotBeSaved($url);
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
        if (!$media->getThumbnails()) {
            throw MediaException::mediaContainsNoThumbnails();
        }

        $delete = $media->getThumbnails()->getIds();

        $delete = \array_values(\array_map(static fn (string $id) => ['id' => $id], $delete));

        $this->thumbnailRepository->delete($delete, $context);
    }

    private function getFileSystem(MediaEntity $media): FilesystemOperator
    {
        if ($media->isPrivate()) {
            return $this->filesystemPrivate;
        }

        return $this->filesystemPublic;
    }

    private function isSameDimension(MediaThumbnailEntity $thumbnail, MediaThumbnailSizeEntity $thumbnailSize): bool
    {
        return $thumbnail->getWidth() === $thumbnailSize->getWidth()
            && $thumbnail->getHeight() === $thumbnailSize->getHeight();
    }
}
