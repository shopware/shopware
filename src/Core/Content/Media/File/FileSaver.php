<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\Exception\CouldNotRenameFileException;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\FileExtensionNotSupportedException;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[Package('content')]
class FileSaver
{
    private readonly FileNameValidator $fileNameValidator;

    /**
     * @internal
     *
     * @param list<string> $allowedExtensions
     * @param list<string> $privateAllowedExtensions
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly FilesystemOperator $filesystemPublic,
        private readonly FilesystemOperator $filesystemPrivate,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ThumbnailService $thumbnailService,
        private readonly MetadataLoader $metadataLoader,
        private readonly TypeDetector $typeDetector,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly array $allowedExtensions,
        private readonly array $privateAllowedExtensions
    ) {
        $this->fileNameValidator = new FileNameValidator();
    }

    /**
     * @throws DuplicatedMediaFileNameException
     * @throws EmptyMediaFilenameException
     * @throws IllegalFileNameException
     * @throws MediaNotFoundException
     * @throws FileExtensionNotSupportedException
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

        $this->saveFileToMediaDir($mediaFile, $media);

        $message = new GenerateThumbnailsMessage();
        $message->setMediaIds([$mediaId]);

        if (Feature::isActive('v6.6.0.0')) {
            $message->setContext($context);
        } else {
            $message->withContext($context);
        }

        $this->messageBus->dispatch($message);
    }

    public function renameMedia(string $mediaId, string $destination, Context $context): void
    {
        $destination = $this->validateFileName($destination);
        $currentMedia = $this->findMediaById($mediaId, $context);
        $fileExtension = $currentMedia->getFileExtension();

        if (!$currentMedia->hasFile() || !$fileExtension) {
            throw new MissingFileException($mediaId);
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
        } catch (\Exception) {
            throw new CouldNotRenameFileException($currentMedia->getId(), (string) $currentMedia->getFileName());
        }

        foreach ($currentMedia->getThumbnails() ?? [] as $thumbnail) {
            try {
                $renamedFiles = [...$renamedFiles, ...$this->renameThumbnail($thumbnail, $currentMedia, $updatedMedia)];
            } catch (\Exception) {
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
        } catch (\Exception) {
            $this->rollbackRenameAction($currentMedia, $renamedFiles);
        }
    }

    /**
     * @return array<string, string>
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
        } catch (UnableToDeleteFile) {
            //nth
        }

        $this->thumbnailService->deleteThumbnails($media, $context);
    }

    private function saveFileToMediaDir(MediaFile $mediaFile, MediaEntity $media): void
    {
        $stream = fopen($mediaFile->getFileName(), 'rb');
        if (!\is_resource($stream)) {
            throw new \RuntimeException('Could not open stream for file ' . $mediaFile->getFileName());
        }
        $path = $this->urlGenerator->getRelativeMediaUrl($media);

        try {
            if (\is_resource($stream)) {
                $this->getFileSystem($media)->writeStream($path, $stream);
            }
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
            'uploadedAt' => new \DateTime(),
        ];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->mediaRepository->update([$data], $context);
        });

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('mediaFolder');

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $context)->get($media->getId());

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

        throw new CouldNotRenameFileException($oldMedia->getId(), (string) $oldMedia->getFileName());
    }

    /**
     * @throws MediaNotFoundException
     */
    private function findMediaById(string $mediaId, Context $context): MediaEntity
    {
        $criteria = new Criteria([$mediaId]);
        $criteria->addAssociation('mediaFolder');
        /** @var MediaEntity|null $currentMedia */
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
     * @throws FileExtensionNotSupportedException
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

        throw new FileExtensionNotSupportedException($mediaId, $fileExtension);
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
            if (
                !$media->hasFile()
                || $destination !== $media->getFileName()
                || $media->isPrivate() !== $currentMedia->isPrivate()
            ) {
                continue;
            }

            throw new DuplicatedMediaFileNameException(
                $destination,
                $fileExtension
            );
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
