<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Shopware\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileNameValidator;
use Shopware\Core\Content\Media\File\MediaFile;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
readonly class PresignedMediaUploadService
{
    private FileNameValidator $fileNameValidator;

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param array<string> $allowedExtensions
     * @param list<string> $privateAllowedExtensions
     */
    public function __construct(
        private EntityRepository $mediaRepository,
        private PresignedUrlGeneratorInterface $presignedUrlGenerator,
        private EventDispatcherInterface $eventDispatcher,
        private TypeDetector $typeDetector,
        private MetadataLoader $metadataLoader,
        private FilesystemOperator $filesystemPublic,
        private FilesystemOperator $filesystemPrivate,
        private ThumbnailService $thumbnailService,
        private MessageBusInterface $messageBus,
        private array $allowedExtensions,
        private array $privateAllowedExtensions,
        private bool $remoteThumbnailsEnable,
    ) {
        $this->fileNameValidator = new FileNameValidator();
    }

    /**
     * Prepare a presigned upload. When payload contains a mediaId, the existing
     * entity is used (replace mode). Otherwise a new entity is created.
     *
     * @return array{mediaId: string, url: string, path: string, expiresAt: string}
     */
    public function prepare(
        PresignedUploadPreparePayload $payload,
        Context $context,
    ): array {
        if (!$payload->fileName) {
            throw MediaException::invalidRequestParameter('fileName');
        }

        if (!$payload->extension) {
            throw MediaException::invalidRequestParameter('extension');
        }

        if (!$payload->mimeType) {
            throw MediaException::invalidRequestParameter('mimeType');
        }

        $fileName = $payload->fileName;
        $extension = $payload->extension;
        $mimeType = $payload->mimeType;

        $this->fileNameValidator->validateFileName($fileName);
        $this->validateFileExtension($extension, $payload->private);

        if ($payload->mediaId !== null) {
            $media = $this->findMedia($payload->mediaId, $context);

            if ($media === null) {
                throw MediaException::mediaNotFound($payload->mediaId);
            }

            $mediaId = $payload->mediaId;
            // Replace: use new timestamp so we upload to a new path and remove the old file in finalize
            $uploadedAt = new \DateTimeImmutable();
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId, $uploadedAt): void {
                $this->mediaRepository->update([
                    ['id' => $mediaId, 'uploadedAt' => \DateTime::createFromImmutable($uploadedAt)],
                ], $context);
            });
        } else {
            $mediaId = Uuid::randomHex();
            $uploadedAt = new \DateTimeImmutable();

            $data = [
                'id' => $mediaId,
                'private' => $payload->private,
                'uploadedAt' => \DateTime::createFromImmutable($uploadedAt),
            ];

            if ($payload->mediaFolderId) {
                $data['mediaFolderId'] = $payload->mediaFolderId;
            }

            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
                $this->mediaRepository->create([$data], $context);
            });
        }

        $isReplace = $payload->mediaId !== null;

        try {
            $location = new MediaLocationStruct(
                $mediaId,
                $extension,
                $fileName,
                $uploadedAt,
            );

            $result = $this->presignedUrlGenerator->generate($location, $mimeType);
        } catch (\Throwable $e) {
            if (!$isReplace) {
                $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId): void {
                    $this->mediaRepository->delete([['id' => $mediaId]], $context);
                });
            }

            throw $e;
        }

        return [
            'mediaId' => $mediaId,
            'url' => $result->url,
            'path' => $result->path,
            'expiresAt' => $result->expiresAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Finalize a presigned upload. Automatically detects replace mode when the
     * media entity already has an existing file and cleans up old data.
     */
    public function finalize(
        string $mediaId,
        PresignedUploadFinalizePayload $payload,
        Context $context,
    ): void {
        $this->validateFinalizePayload($payload);

        $criteria = new Criteria([$mediaId]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository->search($criteria, $context)->getEntities()->first();

        if ($media === null) {
            throw MediaException::mediaNotFound($mediaId);
        }

        $isReplace = $media->hasFile();

        if (!$isReplace) {
            $this->ensureFileNameIsUnique($mediaId, $payload->fileName, $payload->extension, $media->isPrivate(), $context);
        }

        try {
            if (!$this->presignedUrlGenerator->verifyUpload($payload->path)) {
                throw MediaException::presignedUploadFinalizeFailed($mediaId);
            }

            if ($isReplace) {
                $oldPath = $media->getPath();

                if ($oldPath !== '' && $oldPath !== $payload->path) {
                    $this->removeOldMediaData($media, $context);
                } elseif (!$this->remoteThumbnailsEnable) {
                    $this->thumbnailService->deleteThumbnails($media, $context);
                }
            }

            $metadata = $this->presignedUrlGenerator->getFileMetadata($payload->path);
            $fileSize = $metadata !== null ? $metadata->size : 0;

            $mediaFile = new MediaFile('', $payload->mimeType, $payload->extension, $fileSize);
            $mediaType = $this->typeDetector->detect($mediaFile);

            $filesystem = $media->isPrivate() ? $this->filesystemPrivate : $this->filesystemPublic;
            $metaData = $this->loadMetadataFromStorage($payload->path, $mediaFile, $mediaType, $filesystem);

            // Keep the same uploadedAt used in prepare() for path generation – the file was PUT to that path
            $uploadedAt = $media->getUploadedAt() ?? new \DateTime();

            $data = [
                'id' => $mediaId,
                'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
                'mimeType' => $payload->mimeType,
                'fileExtension' => $payload->extension,
                'fileSize' => $fileSize,
                'fileName' => $payload->fileName,
                'mediaTypeRaw' => serialize($mediaType),
                'metaData' => $metaData,
                'uploadedAt' => $uploadedAt,
            ];

            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
                $this->mediaRepository->update([$data], $context);
            });

            $this->eventDispatcher->dispatch(new UpdateMediaPathEvent([$mediaId]));
            $this->eventDispatcher->dispatch(new MediaUploadedEvent($mediaId, $context));

            if (!$this->remoteThumbnailsEnable) {
                $message = new GenerateThumbnailsMessage();
                $message->setMediaIds([$mediaId]);
                $message->setContext($context);

                $this->messageBus->dispatch($message);
            }
        } catch (\Throwable $e) {
            if (!$isReplace) {
                $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId): void {
                    $this->mediaRepository->delete([['id' => $mediaId]], $context);
                });
            }

            throw $e;
        }
    }

    public function isSupported(): bool
    {
        return $this->presignedUrlGenerator->isSupported();
    }

    public function isEnabled(): bool
    {
        return $this->presignedUrlGenerator->isEnabled();
    }

    /**
     * @phpstan-assert non-empty-string $payload->fileName
     * @phpstan-assert non-empty-string $payload->extension
     * @phpstan-assert non-empty-string $payload->mimeType
     * @phpstan-assert non-empty-string $payload->path
     */
    private function validateFinalizePayload(PresignedUploadFinalizePayload $payload): void
    {
        if (!$payload->fileName) {
            throw MediaException::invalidRequestParameter('fileName');
        }

        if (!$payload->extension) {
            throw MediaException::invalidRequestParameter('extension');
        }

        if (!$payload->mimeType) {
            throw MediaException::invalidRequestParameter('mimeType');
        }

        if (!$payload->path) {
            throw MediaException::invalidRequestParameter('path');
        }
    }

    private function findMedia(string $mediaId, Context $context): ?MediaEntity
    {
        return $this->mediaRepository->search(new Criteria([$mediaId]), $context)->getEntities()->first();
    }

    private function ensureFileNameIsUnique(string $mediaId, string $fileName, string $fileExtension, bool $isPrivate, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('fileName', $fileName),
                new EqualsFilter('fileExtension', $fileExtension),
                new NotEqualsFilter('id', $mediaId),
            ]
        ));

        $mediaWithRelatedFileName = $this->mediaRepository->search($criteria, $context)->getEntities();

        foreach ($mediaWithRelatedFileName as $media) {
            if (!$media->hasFile() || $media->isPrivate() !== $isPrivate) {
                continue;
            }

            throw MediaException::duplicatedMediaFileName($fileName, $fileExtension);
        }
    }

    private function validateFileExtension(string $extension, bool $isPrivate): void
    {
        $event = new MediaFileExtensionWhitelistEvent($isPrivate ? $this->privateAllowedExtensions : $this->allowedExtensions);
        $this->eventDispatcher->dispatch($event);

        $fileExtension = mb_strtolower($extension);

        foreach ($event->getWhitelist() as $allowed) {
            if ($fileExtension === mb_strtolower((string) $allowed)) {
                return;
            }
        }

        throw MediaException::fileExtensionNotSupported('', $fileExtension);
    }

    private function removeOldMediaData(MediaEntity $media, Context $context): void
    {
        if (!$media->hasFile()) {
            return;
        }

        $filesystem = $media->isPrivate() ? $this->filesystemPrivate : $this->filesystemPublic;

        try {
            $filesystem->delete($media->getPath());
        } catch (UnableToDeleteFile) {
        }

        if ($this->remoteThumbnailsEnable) {
            return;
        }

        $this->thumbnailService->deleteThumbnails($media, $context);
    }

    /**
     * Load metadata (width, height, etc.) from a file on storage.
     * Downloads to temp file because MetadataLoader uses getimagesize() and similar which need a local path.
     *
     * @return array<string, mixed>|null
     */
    private function loadMetadataFromStorage(
        string $path,
        MediaFile $mediaFile,
        MediaType $mediaType,
        FilesystemOperator $filesystem,
    ): ?array {
        $tempFile = tempnam(sys_get_temp_dir(), 'sw_media_meta');
        if ($tempFile === false) {
            return null;
        }

        try {
            $sourceStream = $filesystem->readStream($path);
            if (!\is_resource($sourceStream)) {
                return null;
            }

            $destStream = $this->openTempFileForWrite($tempFile);
            if ($destStream === null) {
                fclose($sourceStream);

                return null;
            }

            try {
                stream_copy_to_stream($sourceStream, $destStream);
            } finally {
                fclose($sourceStream);
                fclose($destStream);
            }

            // MD5 of file content – stored in metaData.hash, used for deduplication and file_hash index
            $fileHash = Hasher::hashFile($tempFile, 'md5');

            $mediaFileForMeta = new MediaFile(
                $tempFile,
                $mediaFile->getMimeType(),
                $mediaFile->getFileExtension(),
                $mediaFile->getFileSize(),
                $fileHash,
            );

            return $this->metadataLoader->loadFromFile($mediaFileForMeta, $mediaType);
        } catch (\Throwable) {
            return null;
        } finally {
            if (\is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @return resource|null
     */
    private function openTempFileForWrite(string $path)
    {
        $stream = @fopen($path, 'w');
        if ($stream === false) {
            return null;
        }

        return $stream;
    }
}
