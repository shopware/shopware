<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\Event\MediaPathChangedEvent;
use Shopware\Core\Content\Media\Infrastructure\Path\SqlMediaLocationBuilder;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[Package('buyers-experience')]
class FileSaver
{
    private readonly FileNameValidator $fileNameValidator;

    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param array<string> $allowedExtensions
     * @param list<string> $privateAllowedExtensions
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly FilesystemOperator $filesystemPublic,
        private readonly FilesystemOperator $filesystemPrivate,
        private readonly ThumbnailService $thumbnailService,
        private readonly MetadataLoader $metadataLoader,
        private readonly TypeDetector $typeDetector,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SqlMediaLocationBuilder $locationBuilder,
        private readonly AbstractMediaPathStrategy $mediaPathStrategy,
        private readonly array $allowedExtensions,
        private readonly array $privateAllowedExtensions
    ) {
        $this->fileNameValidator = new FileNameValidator();
    }

    /**
     * @throws MediaException
     */
    public function persistFileToMedia(
        MediaFile $mediaFile,
        string $destination,
        string $mediaId,
        Context $context
    ): void {
        $currentMedia = $this->findMediaById($mediaId, $context);
        $destination = $this->validateFileName($destination);
        $this->ensureFileNameIsUnique(
            $currentMedia,
            $destination,
            $mediaFile->getFileExtension(),
            $context
        );

        $this->validateFileExtension($mediaFile, $mediaId, $currentMedia->isPrivate());

        $this->removeOldMediaData($currentMedia, $context);

        $mediaType = $this->typeDetector->detect($mediaFile);

        $metaData = $this->metadataLoader->loadFromFile($mediaFile, $mediaType);

        $media = $this->updateMediaEntity(
            $mediaFile,
            $destination,
            $currentMedia,
            $metaData,
            $mediaType,
            $context
        );

        $this->saveFileToMediaDir($mediaFile, $media, $context);

        $message = new GenerateThumbnailsMessage();
        $message->setMediaIds([$mediaId]);
        $message->setContext($context);

        $this->messageBus->dispatch($message);
    }

    public function renameMedia(string $mediaId, string $destination, Context $context): void
    {
        $destination = $this->validateFileName($destination);
        $currentMedia = $this->findMediaById($mediaId, $context);
        $fileExtension = $currentMedia->getFileExtension();

        if (!$currentMedia->hasFile() || !$fileExtension) {
            throw MediaException::missingFile($mediaId);
        }

        if ($destination === $currentMedia->getFileName()) {
            return;
        }

        $this->ensureFileNameIsUnique(
            $currentMedia,
            $destination,
            $fileExtension,
            $context
        );

        $this->doRenameMedia($currentMedia, $destination, $context);
    }

    private function doRenameMedia(MediaEntity $media, string $destination, Context $context): void
    {
        $path = $this->getNewMediaPath($media, $destination);

        $thumbnails = $this->getNewThumbnailPaths($media, $destination);

        try {
            $renamedFiles = $this->renameFile(
                $media->getPath(),
                $path,
                $this->getFileSystem($media)
            );
        } catch (\Exception) {
            throw MediaException::couldNotRenameFile($media->getId(), (string) $media->getFileName());
        }

        foreach ($media->getThumbnails() ?? [] as $thumbnail) {
            try {
                $thumbnailDestination = $thumbnails[$thumbnail->getUniqueIdentifier()];

                $renamedFiles = [...$renamedFiles, ...$this->renameThumbnail($thumbnail, $media, $thumbnailDestination)];
            } catch (\Exception) {
                $this->rollbackRenameAction($media, $renamedFiles);
            }
        }
        $event = new MediaPathChangedEvent($context);

        $event->media(
            mediaId: $media->getId(),
            path: $path
        );

        $updateData = [
            'id' => $media->getId(),
            'fileName' => $destination,
            'path' => $path,
        ];

        if (!empty($thumbnails)) {
            foreach ($thumbnails as $thumbnailId => $thumbnailPath) {
                $event->thumbnail(
                    mediaId: $media->getId(),
                    thumbnailId: $thumbnailId,
                    path: $thumbnailPath
                );
            }

            $updateData['thumbnails'] = array_map(function ($id, $path) {
                return ['id' => $id, 'path' => $path];
            }, array_keys($thumbnails), $thumbnails);
        }

        try {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($updateData): void {
                // also triggers the indexing, so that the thumbnails_ro is recalculated
                $this->mediaRepository->update([$updateData], $context);
            });

            $this->eventDispatcher->dispatch($event);
        } catch (\Exception) {
            $this->rollbackRenameAction($media, $renamedFiles);
        }
    }

    /**
     * @return array<string, string>
     */
    private function renameThumbnail(
        MediaThumbnailEntity $thumbnail,
        MediaEntity $currentMedia,
        string $destination
    ): array {
        return $this->renameFile(
            $thumbnail->getPath(),
            $destination,
            $this->getFileSystem($currentMedia)
        );
    }

    private function removeOldMediaData(MediaEntity $media, Context $context): void
    {
        if (!$media->hasFile()) {
            return;
        }

        try {
            $this->getFileSystem($media)->delete($media->getPath());
        } catch (UnableToDeleteFile) {
            // nth
        }

        $this->thumbnailService->deleteThumbnails($media, $context);
    }

    private function saveFileToMediaDir(MediaFile $mediaFile, MediaEntity $media, Context $context): void
    {
        $stream = fopen($mediaFile->getFileName(), 'rb');
        if (!\is_resource($stream)) {
            throw MediaException::cannotOpenSourceStreamToRead($mediaFile->getFileName());
        }

        $path = $media->getPath();

        $event = new MediaPathChangedEvent($context);
        $event->media(mediaId: $media->getId(), path: $path);

        try {
            $this->getFileSystem($media)->writeStream($path, $stream);

            $this->eventDispatcher->dispatch($event);
        } finally {
            // The Google Cloud Storage filesystem closes the stream even though it should not. To prevent a fatal
            // error, we therefore need to check whether the stream has been closed yet.
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function getFileSystem(MediaEntity $media): FilesystemOperator
    {
        if ($media->isPrivate()) {
            return $this->filesystemPrivate;
        }

        return $this->filesystemPublic;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function updateMediaEntity(
        MediaFile $mediaFile,
        string $destination,
        MediaEntity $media,
        ?array $metadata,
        MediaType $mediaType,
        Context $context
    ): MediaEntity {
        $data = [
            'id' => $media->getId(),
            'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
            'mimeType' => $mediaFile->getMimeType(),
            'fileExtension' => $mediaFile->getFileExtension(),
            'fileSize' => $mediaFile->getFileSize(),
            'fileName' => $destination,
            'metaData' => $metadata,
            'mediaTypeRaw' => serialize($mediaType),
        ];

        if ($media->getUploadedAt() === null) {
            $data['uploadedAt'] = new \DateTime();
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->mediaRepository->update([$data], $context);
        });

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('mediaFolder');

        $this->eventDispatcher->dispatch(new UpdateMediaPathEvent([$media->getId()]));

        $media = $this->mediaRepository->search($criteria, $context)->getEntities()->get($media->getId());
        \assert($media !== null);

        return $media;
    }

    /**
     * @return array<string, string>
     */
    private function renameFile(string $source, string $destination, FilesystemOperator $filesystem): array
    {
        $filesystem->move($source, $destination);

        return [$source => $destination];
    }

    /**
     * @param array<string, string> $renamedFiles
     */
    private function rollbackRenameAction(MediaEntity $oldMedia, array $renamedFiles): void
    {
        foreach ($renamedFiles as $oldFileName => $newFileName) {
            $this->getFileSystem($oldMedia)->move($newFileName, $oldFileName);
        }

        throw MediaException::couldNotRenameFile($oldMedia->getId(), (string) $oldMedia->getFileName());
    }

    /**
     * @throws MediaException
     */
    private function findMediaById(string $mediaId, Context $context): MediaEntity
    {
        $criteria = new Criteria([$mediaId]);
        $criteria->addAssociation('mediaFolder');
        $currentMedia = $this->mediaRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($mediaId);

        if ($currentMedia === null) {
            throw MediaException::mediaNotFound($mediaId);
        }

        return $currentMedia;
    }

    /**
     * @throws MediaException
     */
    private function validateFileName(string $destination): string
    {
        $destination = rtrim($destination);
        $this->fileNameValidator->validateFileName($destination);

        return $destination;
    }

    /**
     * @throws MediaException
     */
    private function validateFileExtension(MediaFile $mediaFile, string $mediaId, bool $isPrivate = false): void
    {
        $event = new MediaFileExtensionWhitelistEvent($isPrivate ? $this->privateAllowedExtensions : $this->allowedExtensions);
        $this->eventDispatcher->dispatch($event);

        $fileExtension = mb_strtolower($mediaFile->getFileExtension());

        foreach ($event->getWhitelist() as $extension) {
            if ($fileExtension === mb_strtolower((string) $extension)) {
                return;
            }
        }

        throw MediaException::fileExtensionNotSupported($mediaId, $fileExtension);
    }

    /**
     * @throws MediaException
     */
    private function ensureFileNameIsUnique(
        MediaEntity $currentMedia,
        string $destination,
        string $fileExtension,
        Context $context
    ): void {
        $mediaWithRelatedFileName = $this->searchRelatedMediaByFileName(
            $currentMedia,
            $destination,
            $fileExtension,
            $context
        );

        foreach ($mediaWithRelatedFileName as $media) {
            if (
                !$media->hasFile()
                || $destination !== $media->getFileName()
                || $media->isPrivate() !== $currentMedia->isPrivate()
            ) {
                continue;
            }

            throw MediaException::duplicatedMediaFileName($destination, $fileExtension);
        }
    }

    private function searchRelatedMediaByFileName(
        MediaEntity $media,
        string $destination,
        string $fileExtension,
        Context $context
    ): MediaCollection {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('fileName', $destination),
                new EqualsFilter('fileExtension', $fileExtension),
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [new EqualsFilter('id', $media->getId())]
                ),
            ]
        ));

        return $this->mediaRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @return array<string, string>
     */
    private function getNewThumbnailPaths(MediaEntity $media, string $destination): array
    {
        if (!$media->getThumbnails()) {
            return [];
        }

        $locations = $this->locationBuilder->thumbnails($media->getThumbnails()->getIds());

        foreach ($locations as $location) {
            $location->media->fileName = $destination;
        }

        return $this->mediaPathStrategy->generate($locations);
    }

    private function getNewMediaPath(MediaEntity $currentMedia, string $destination): string
    {
        $locations = $this->locationBuilder->media([$currentMedia->getId()]);
        $location = $locations[$currentMedia->getId()];
        $location->fileName = $destination;

        $paths = $this->mediaPathStrategy->generate($locations);

        return $paths[$currentMedia->getId()];
    }
}
