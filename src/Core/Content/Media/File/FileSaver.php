<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\Exception\CouldNotRenameFileException;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\MissingFileException;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class FileSaver
{
    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var FilesystemInterface
     */
    private $filesystemPublic;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var FileNameValidator
     */
    private $fileNameValidator;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var MetadataLoader
     */
    private $metadataLoader;

    /**
     * @var TypeDetector
     */
    private $typeDetector;

    /**
     * @var FilesystemInterface
     */
    private $filesystemPrivate;

    /**
     * @var array
     */
    private $whitelist;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $mediaRepository,
        FilesystemInterface $filesystemPublic,
        FilesystemInterface $filesystemPrivate,
        UrlGeneratorInterface $urlGenerator,
        ThumbnailService $thumbnailService,
        MetadataLoader $metadataLoader,
        TypeDetector $typeDetector,
        MessageBusInterface $messageBus,
        EventDispatcherInterface $eventDispatcher,
        array $allowedExtensions
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->filesystemPublic = $filesystemPublic;
        $this->filesystemPrivate = $filesystemPrivate;
        $this->urlGenerator = $urlGenerator;
        $this->thumbnailService = $thumbnailService;
        $this->fileNameValidator = new FileNameValidator();
        $this->metadataLoader = $metadataLoader;
        $this->typeDetector = $typeDetector;
        $this->messageBus = $messageBus;
        $this->eventDispatcher = $eventDispatcher;
        $this->whitelist = $allowedExtensions;
    }

    /**
     * @throws DuplicatedMediaFileNameException
     * @throws EmptyMediaFilenameException
     * @throws IllegalFileNameException
     * @throws MediaNotFoundException
     * @throws FileTypeNotSupportedException
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

        $this->validateFileExtension($mediaFile, $mediaId);
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

        $this->saveFileToMediaDir($mediaFile, $media);

        $message = new GenerateThumbnailsMessage();
        $message->setMediaIds([$mediaId]);
        $message->withContext($context);

        $this->messageBus->dispatch($message);
    }

    /**
     * @throws CouldNotRenameFileException
     * @throws DuplicatedMediaFileNameException
     * @throws FileExistsException
     * @throws MediaNotFoundException
     * @throws MissingFileException
     * @throws EmptyMediaFilenameException
     * @throws IllegalFileNameException
     */
    public function renameMedia(string $mediaId, string $destination, Context $context): void
    {
        $destination = $this->validateFileName($destination);
        $currentMedia = $this->findMediaById($mediaId, $context);

        if (!$currentMedia->hasFile()) {
            throw new MissingFileException($mediaId);
        }

        if ($destination === $currentMedia->getFileName()) {
            return;
        }

        $this->ensureFileNameIsUnique(
            $currentMedia,
            $destination,
            $currentMedia->getFileExtension(),
            $context
        );

        $this->doRenameMedia($currentMedia, $destination, $context);
    }

    /**
     * @throws CouldNotRenameFileException
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function doRenameMedia(MediaEntity $currentMedia, string $destination, Context $context): void
    {
        $updatedMedia = clone $currentMedia;
        $updatedMedia->setFileName($destination);
        $updatedMedia->setUploadedAt(new \DateTime());

        try {
            $renamedFiles = $this->renameFile(
                $this->urlGenerator->getRelativeMediaUrl($currentMedia),
                $this->urlGenerator->getRelativeMediaUrl($updatedMedia),
                $this->getFileSystem($currentMedia)
            );
        } catch (\Exception $e) {
            throw new CouldNotRenameFileException($currentMedia->getId(), $currentMedia->getFileName());
        }

        foreach ($currentMedia->getThumbnails() as $thumbnail) {
            try {
                $renamedFiles = array_merge(
                    $renamedFiles,
                    $this->renameThumbnail($thumbnail, $currentMedia, $updatedMedia)
                );
            } catch (\Exception $e) {
                $this->rollbackRenameAction($currentMedia, $renamedFiles);
            }
        }

        $updateData = [
            'id' => $updatedMedia->getId(),
            'fileName' => $updatedMedia->getFileName(),
            'uploadedAt' => $updatedMedia->getUploadedAt(),
        ];

        try {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($updateData): void {
                $this->mediaRepository->update([$updateData], $context);
            });
        } catch (\Exception $e) {
            $this->rollbackRenameAction($currentMedia, $renamedFiles);
        }
    }

    /**
     * @throws CouldNotRenameFileException
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function renameThumbnail(
        MediaThumbnailEntity $thumbnail,
        MediaEntity $currentMedia,
        MediaEntity $updatedMedia
    ): array {
        return $this->renameFile(
            $this->urlGenerator->getRelativeThumbnailUrl(
                $currentMedia,
                $thumbnail
            ),
            $this->urlGenerator->getRelativeThumbnailUrl(
                $updatedMedia,
                $thumbnail
            ),
            $this->getFileSystem($currentMedia)
        );
    }

    private function removeOldMediaData(MediaEntity $media, Context $context): void
    {
        if (!$media->hasFile()) {
            return;
        }

        $oldMediaFilePath = $this->urlGenerator->getRelativeMediaUrl($media);

        try {
            $this->getFileSystem($media)->delete($oldMediaFilePath);
        } catch (FileNotFoundException $e) {
            //nth
        }

        $this->thumbnailService->deleteThumbnails($media, $context);
    }

    private function saveFileToMediaDir(MediaFile $mediaFile, MediaEntity $media): void
    {
        $stream = fopen($mediaFile->getFileName(), 'rb');
        $path = $this->urlGenerator->getRelativeMediaUrl($media);

        try {
            $this->getFileSystem($media)->putStream($path, $stream);
        } finally {
            // The Google Cloud Storage filesystem closes the stream even though it should not. To prevent a fatal
            // error, we therefore need to check whether the stream has been closed yet.
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function getFileSystem(MediaEntity $media): FilesystemInterface
    {
        if ($media->isPrivate()) {
            return $this->filesystemPrivate;
        }

        return $this->filesystemPublic;
    }

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
            'uploadedAt' => new \DateTime(),
        ];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->mediaRepository->update([$data], $context);
        });

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('mediaFolder');

        return $this->mediaRepository->search($criteria, $context)->get($media->getId());
    }

    /**
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function renameFile(string $source, string $destination, FilesystemInterface $filesystem): array
    {
        $filesystem->rename($source, $destination);

        return [$source => $destination];
    }

    /**
     * @throws CouldNotRenameFileException
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function rollbackRenameAction(MediaEntity $oldMedia, array $renamedFiles): void
    {
        foreach ($renamedFiles as $oldFileName => $newFileName) {
            $this->getFileSystem($oldMedia)->rename($newFileName, $oldFileName);
        }

        throw new CouldNotRenameFileException($oldMedia->getId(), $oldMedia->getFileName());
    }

    /**
     * @throws MediaNotFoundException
     */
    private function findMediaById(string $mediaId, Context $context): MediaEntity
    {
        $criteria = new Criteria([$mediaId]);
        $criteria->addAssociation('mediaFolder');
        $currentMedia = $this->mediaRepository
            ->search($criteria, $context)
            ->get($mediaId);

        if ($currentMedia === null) {
            throw new MediaNotFoundException($mediaId);
        }

        return $currentMedia;
    }

    /**
     * @throws EmptyMediaFilenameException
     * @throws IllegalFileNameException
     */
    private function validateFileName(string $destination): string
    {
        $destination = rtrim($destination);
        $this->fileNameValidator->validateFileName($destination);

        return $destination;
    }

    /**
     * @throws FileTypeNotSupportedException
     */
    private function validateFileExtension(MediaFile $mediaFile, string $mediaId): void
    {
        $event = new MediaFileExtensionWhitelistEvent($this->whitelist);
        $this->eventDispatcher->dispatch($event);

        foreach ($event->getWhitelist() as $extension) {
            if (strtolower($mediaFile->getFileExtension()) === strtolower($extension)) {
                return;
            }
        }

        throw new FileTypeNotSupportedException($mediaId);
    }

    /**
     * @throws DuplicatedMediaFileNameException
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
            if ($media->hasFile() && $destination === $media->getFileName()) {
                throw new DuplicatedMediaFileNameException(
                    $destination,
                    $fileExtension
                );
            }
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

        /** @var MediaCollection $mediaCollection */
        $mediaCollection = $this->mediaRepository->search($criteria, $context)->getEntities();

        return $mediaCollection;
    }
}
