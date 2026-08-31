<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\Core\Event\UpdateThumbnailPathEvent;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Shopware\Core\Content\Media\Event\MediaPathChangedEvent;
use Shopware\Core\Content\Media\Event\ThumbnailGeneratedEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\Subscriber\MediaDeletionSubscriber;
use Shopware\Core\Content\Media\Thumbnail\Processor\ThumbnailProcessorInterface;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-type ImageSize array{width: int<1, max>, height: int<1, max>}
 */
#[Package('discovery')]
class ThumbnailService
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaThumbnailCollection> $thumbnailRepository
     * @param EntityRepository<MediaFolderCollection> $mediaFolderRepository
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
        private readonly ThumbnailProcessorInterface $thumbnailProcessor,
        private readonly LoggerInterface $logger,
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

            if (MediaUploadService::isExternalUrl($media->getPath())) {
                continue;
            }

            if (!$this->canAutoGenerateThumbnails($media, $context)) {
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

        if ($delete !== []) {
            $context->addState(MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);

            $delete = \array_values(\array_map(static fn (string $id) => ['id' => $id], $delete));

            $this->thumbnailRepository->delete($delete, $context);
        }

        $updates = [];
        foreach ($generate as $media) {
            if ($media->getMediaFolder() === null || $media->getMediaFolder()->getConfiguration() === null) {
                continue;
            }

            $config = $media->getMediaFolder()->getConfiguration();

            try {
                $thumbnails = $this->generateAndSave($media, $config, $context, $config->getMediaThumbnailSizes());
            } catch (\Throwable $e) {
                $this->logger->error('Thumbnail generation failed for media {mediaId}', [
                    'mediaId' => $media->getId(),
                    'exception' => $e,
                ]);

                continue;
            }

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
    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'force', parameterType: 'bool', defaultValue: false, description: 'Regenerates thumbnails for all configured sizes even when a thumbnail already exists.')]
    public function updateThumbnails(MediaEntity $media, Context $context, bool $strict/* , bool $force = false */): int
    {
        /** @deprecated tag:v6.8.0 - Remove next line as $force will become part of the method signature */
        $force = (bool) (\func_get_args()[3] ?? false);

        if ($this->remoteThumbnailsEnable) {
            throw MediaException::thumbnailGenerationDisabled();
        }

        if (MediaUploadService::isExternalUrl($media->getPath())) {
            return 0;
        }

        if (!$this->canAutoGenerateThumbnails($media, $context)) {
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

        if ($config->getMediaThumbnailSizes() === null) {
            return 0;
        }
        if ($media->getThumbnails() === null) {
            return 0;
        }

        $toBeCreatedSizes = new MediaThumbnailSizeCollection($config->getMediaThumbnailSizes()->getElements());
        $toBeDeletedThumbnails = new MediaThumbnailCollection($media->getThumbnails()->getElements());

        if (!$force) {
            foreach ($toBeCreatedSizes as $thumbnailSize) {
                foreach ($toBeDeletedThumbnails as $thumbnail) {
                    if ($thumbnailSize->getId() !== $thumbnail->getMediaThumbnailSizeId()) {
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
        }

        $delete = \array_values(\array_map(static fn (string $id) => ['id' => $id], $toBeDeletedThumbnails->getIds()));

        $toBeDeletedPaths = [];
        foreach ($toBeDeletedThumbnails as $thumbnail) {
            $path = $thumbnail->getPath();
            if (!MediaUploadService::isExternalUrl($path)) {
                $toBeDeletedPaths[] = $path;
            }
        }

        /**
         * The physical files are deleted after the transaction has been committed, so a failed
         * regeneration rolls back to the previous thumbnails instead of leaving records without files.
         */
        $update = RetryableTransaction::transactional($this->connection, function () use ($delete, $media, $config, $context, $toBeCreatedSizes, $toBeDeletedPaths): array {
            return $context->state(function () use ($delete, $media, $config, $context, $toBeCreatedSizes, $toBeDeletedPaths): array {
                $this->thumbnailRepository->delete($delete, $context);

                $updated = $this->generateAndSave($media, $config, $context, $toBeCreatedSizes, $toBeDeletedPaths);

                $this->indexer->handle(new MediaIndexingMessage([$media->getId()]));

                return $updated;
            }, EntityIndexerRegistry::DISABLE_INDEXING, MediaDeletionSubscriber::SKIP_FILE_DELETE);
        });

        /** @var list<array{id:string, mediaId:string, mediaThumbnailSizeId:string, width:int, height:int}> $update */
        $this->deleteStaleThumbnailFiles($media, $toBeDeletedPaths, $update);

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
     * Deletes files of replaced thumbnails whose path is no longer referenced. Regenerated
     * thumbnails of an unchanged size overwrite their previous file in place, so only paths
     * of dropped sizes remain to be cleaned up.
     *
     * @param list<string> $oldPaths
     * @param list<array{id:string, mediaId:string, mediaThumbnailSizeId:string, width:int, height:int}> $updated
     */
    private function deleteStaleThumbnailFiles(MediaEntity $media, array $oldPaths, array $updated): void
    {
        if ($oldPaths === []) {
            return;
        }

        $ids = \array_column($updated, 'id');

        $newPaths = [];
        if ($ids !== []) {
            $newPaths = $this->connection->fetchFirstColumn(
                <<<'SQL'
                SELECT `path`
                FROM `media_thumbnail`
                WHERE `id` IN (:ids)
                SQL,
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => ArrayParameterType::BINARY]
            );
        }

        $fileSystem = $this->getFileSystem($media);
        foreach (\array_diff($oldPaths, $newPaths) as $stalePath) {
            try {
                $fileSystem->delete($stalePath);
            } catch (\Throwable $e) {
                $this->logger->error('Could not delete stale thumbnail file {path}', [
                    'path' => $stalePath,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * @param list<string> $preservePaths Paths of previous thumbnails which are overwritten in place and must survive a failed generation
     *
     * @return list<array{id:string, mediaId:string, mediaThumbnailSizeId:string, width:int, height:int}>
     */
    private function generateAndSave(MediaEntity $media, MediaFolderConfigurationEntity $config, Context $context, ?MediaThumbnailSizeCollection $sizes, array $preservePaths = []): array
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

        foreach ($sizes as $size) {
            $id = Uuid::randomHex();

            $thumbnailSize = $this->calculateThumbnailSize($imageSize, $size, $config);

            $records[] = [
                'id' => $id,
                'mediaId' => $media->getId(),
                'mediaThumbnailSizeId' => $size->getId(),
                'width' => $thumbnailSize['width'],
                'height' => $thumbnailSize['height'],
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

        $writtenPaths = [];
        $fileSystem = $this->getFileSystem($media);

        try {
            $event = new MediaPathChangedEvent($context);

            foreach ($records as $record) {
                $thumbnailSize = ['width' => $record['width'], 'height' => $record['height']];

                $thumbnail = $this->thumbnailProcessor->createNewImage($image, $type, $imageSize, $thumbnailSize);

                $id = $record['id'];
                $path = $paths[$id];

                $this->writeThumbnail($thumbnail, $media, $path, $config->getThumbnailQuality());
                $writtenPaths[] = $path;

                if ($imageSize === $thumbnailSize && $fileSystem->fileSize($media->getPath()) < $fileSystem->fileSize($path)) {
                    $fileSystem->write($path, $fileSystem->read($media->getPath()));
                }

                $this->dispatcher->dispatch(new ThumbnailGeneratedEvent(
                    $media->getId(),
                    $id,
                    $path,
                    $media->getMimeType() ?? '',
                    $fileSystem,
                    $context,
                ));

                $event->thumbnailWithMimeType(
                    mediaId: $media->getId(),
                    thumbnailId: $id,
                    path: $path,
                    mimeType: $media->getMimeType()
                );
            }

            $this->dispatcher->dispatch($event);
        } catch (\Throwable $e) {
            $fileSystem = $this->getFileSystem($media);
            foreach ($writtenPaths as $writtenPath) {
                if (\in_array($writtenPath, $preservePaths, true)) {
                    continue;
                }

                try {
                    $fileSystem->delete($writtenPath);
                } catch (\Throwable) {
                }
            }

            throw $e;
        }

        return $records;
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

        $folder = $this->mediaFolderRepository->search($criteria, $context)->getEntities()->get($mediaFolderId);
        if ($folder === null) {
            return;
        }

        $media->setMediaFolder($folder);
    }

    private function getImageResource(MediaEntity $media): object
    {
        $filePath = $media->getPath();

        $file = $this->getFileSystem($media)->read($filePath);

        try {
            $image = $this->thumbnailProcessor->createImageFromString($file);
        } catch (\Throwable) {
            throw MediaException::thumbnailNotSupported($media->getId());
        }

        if (\function_exists('exif_read_data')) {
            $stream = fopen('php://memory', 'r+');
            \assert(\is_resource($stream));

            try {
                // use in-memory stream to read the EXIF-metadata,
                // to avoid downloading the image twice from a remote filesystem
                fwrite($stream, $file);
                rewind($stream);

                $exif = @exif_read_data($stream);

                if ($exif !== false) {
                    $exifOrientation = $exif['Orientation'] ?? null;
                    if ($exifOrientation === 8) {
                        $image = $this->thumbnailProcessor->rotate($image, 90);
                    } elseif ($exifOrientation === 3) {
                        $image = $this->thumbnailProcessor->rotate($image, 180);
                    } elseif ($exifOrientation === 6) {
                        $image = $this->thumbnailProcessor->rotate($image, -90);
                    }
                }
            } catch (\Exception) {
                // Ignore.
            } finally {
                fclose($stream);
            }
        }

        return $image;
    }

    /**
     * @return ImageSize
     */
    private function getOriginalImageSize(object $image): array
    {
        return [
            'width' => $this->thumbnailProcessor->getWidth($image),
            'height' => $this->thumbnailProcessor->getHeight($image),
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

    private function writeThumbnail(object $thumbnail, MediaEntity $media, string $url, int $quality): void
    {
        try {
            $imageFile = $this->thumbnailProcessor->convertImage($thumbnail, (string) $media->getMimeType(), $quality);
        } catch (MediaException) {
            throw MediaException::thumbnailCouldNotBeSaved($url);
        }

        try {
            $this->getFileSystem($media)->write($url, $imageFile);
        } catch (\Exception) {
            throw MediaException::thumbnailCouldNotBeSaved($url);
        }
    }

    private function canAutoGenerateThumbnails(MediaEntity $media, Context $context): bool
    {
        if (!$media->hasFile()) {
            return false;
        }

        if (MediaUploadService::isExternalUrl($media->getPath())) {
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
}
