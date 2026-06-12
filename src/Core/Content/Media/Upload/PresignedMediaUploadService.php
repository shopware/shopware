<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Event\MediaPathChangedEvent;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileNameValidator;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\AudioType;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PresignedMediaUploadService
{
    private FileNameValidator $fileNameValidator;

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private EntityRepository $mediaRepository,
        private PresignedUrlGeneratorInterface $presignedUrlGenerator,
        private EventDispatcherInterface $eventDispatcher,
        private TypeDetector $typeDetector,
        private MediaFileCleanupService $mediaFileCleanup,
        private MediaFileExtensionValidator $extensionValidator,
        private AbstractMediaPathStrategy $mediaPathStrategy,
        private LoggerInterface $logger,
    ) {
        $this->fileNameValidator = new FileNameValidator();
    }

    public function prepare(
        PresignedUploadPreparePayload $payload,
        Context $context,
    ): PresignedUploadPrepareResult {
        $this->fileNameValidator->validateFileName($payload->fileName);

        ['mediaId' => $mediaId, 'uploadedAt' => $uploadedAt] = $this->resolveMediaForPrepare($payload, $context);

        $isReplace = $payload->mediaId !== null;

        $isDuplicate = false;
        if (!$isReplace) {
            $isDuplicate = $this->isFileNameTaken($mediaId, $payload->fileName, $payload->extension, $payload->private, $context);
        }

        try {
            $result = $this->generatePresignedUrl($mediaId, $payload, $uploadedAt);
        } catch (\Throwable $e) {
            if (!$isReplace) {
                $this->deleteMediaEntity($mediaId, $context);
            }

            throw $e;
        }

        // For replace, the new uploadedAt is persisted only after the presign URL is signed. This
        // avoids leaving the entity's uploadedAt out-of-sync with the stored path when presigning fails
        if ($isReplace) {
            $this->persistReplaceUploadedAt($mediaId, $uploadedAt, $context);
        }

        return new PresignedUploadPrepareResult(
            mediaId: $mediaId,
            url: $result->url,
            path: $result->path,
            expiresAt: $result->expiresAt->format(\DateTimeInterface::ATOM),
            isDuplicate: $isDuplicate,
        );
    }

    public function finalize(
        string $mediaId,
        PresignedUploadFinalizePayload $payload,
        Context $context,
    ): void {
        $media = $this->findMediaWithThumbnails($mediaId, $context);
        $isReplace = $media->hasFile();

        $this->validateFinalizeRequest($mediaId, $payload, $media, $context);

        try {
            if (!$isReplace) {
                $this->ensureFileNameIsUnique($mediaId, $payload->fileName, $payload->extension, $media->isPrivate(), $context);
            }

            $s3Metadata = $this->verifyFileOnStorage($mediaId, $payload->path);

            if ($isReplace) {
                $this->cleanupOldMediaData($media, $payload->path, $context);
            }

            $mimeType = $s3Metadata->contentType ?? $payload->mimeType;

            $this->persistMediaData($mediaId, $payload, $s3Metadata, $mimeType, $media, $context);
            $this->dispatchFinalizeEvents($mediaId, $payload->path, $mimeType, $context);
        } catch (\Throwable $e) {
            if (!$isReplace) {
                $this->presignedUrlGenerator->deleteFromStorage($payload->path);
                $this->deleteMediaEntity($mediaId, $context);
            }

            throw $e;
        }
    }

    public function isAvailable(): bool
    {
        return $this->presignedUrlGenerator->isSupported();
    }

    /**
     * @return array{mediaId: string, uploadedAt: \DateTimeImmutable}
     */
    private function resolveMediaForPrepare(PresignedUploadPreparePayload $payload, Context $context): array
    {
        if ($payload->mediaId !== null) {
            $media = $this->findMedia($payload->mediaId, $context);

            if ($media === null) {
                throw MediaException::mediaNotFound($payload->mediaId);
            }

            $this->extensionValidator->validate($payload->extension, $media->isPrivate(), $context, $payload->mediaId);

            return ['mediaId' => $payload->mediaId, 'uploadedAt' => new \DateTimeImmutable()];
        }

        $this->extensionValidator->validate($payload->extension, $payload->private, $context);

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

        return ['mediaId' => $mediaId, 'uploadedAt' => $uploadedAt];
    }

    private function generatePresignedUrl(string $mediaId, PresignedUploadPreparePayload $payload, \DateTimeImmutable $uploadedAt): PresignedUrlResult
    {
        $location = new MediaLocationStruct(
            $mediaId,
            $payload->extension,
            $payload->fileName,
            $uploadedAt,
        );

        return $this->presignedUrlGenerator->generate($location, $payload->mimeType);
    }

    private function findMediaWithThumbnails(string $mediaId, Context $context): MediaEntity
    {
        $criteria = new Criteria([$mediaId]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository->search($criteria, $context)->getEntities()->first();

        if ($media === null) {
            throw MediaException::mediaNotFound($mediaId);
        }

        return $media;
    }

    /**
     * Runs the attacker-controllable validations that must NOT trigger storage cleanup on failure.
     * The caller must keep this invocation outside the try/catch that deletes $payload->path.
     */
    private function validateFinalizeRequest(string $mediaId, PresignedUploadFinalizePayload $payload, MediaEntity $media, Context $context): void
    {
        $this->extensionValidator->validate($payload->extension, $media->isPrivate(), $context, $mediaId);
        $this->validateExpectedPath($mediaId, $payload, $media);
    }

    private function verifyFileOnStorage(string $mediaId, string $path): FileMetadataResult
    {
        $s3Metadata = $this->presignedUrlGenerator->getFileMetadata($path);

        if ($s3Metadata === null) {
            $this->logger->error('Could not verify presigned upload for media "{mediaId}": file not found on storage at path "{path}"', [
                'mediaId' => $mediaId,
                'path' => $path,
            ]);

            throw MediaException::presignedUploadFinalizeFailed($mediaId);
        }

        return $s3Metadata;
    }

    private function cleanupOldMediaData(MediaEntity $media, string $newPath, Context $context): void
    {
        $oldPath = $media->getPath();

        if ($oldPath !== '' && $oldPath !== $newPath) {
            $this->mediaFileCleanup->removeOldMediaData($media, $context);
        } else {
            $this->mediaFileCleanup->deleteThumbnails($media, $context);
        }
    }

    private function persistMediaData(
        string $mediaId,
        PresignedUploadFinalizePayload $payload,
        FileMetadataResult $s3Metadata,
        string $mimeType,
        MediaEntity $media,
        Context $context,
    ): void {
        $mediaType = $this->detectMediaType($mimeType, $payload->extension);

        $data = [
            'id' => $mediaId,
            'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
            'mimeType' => $mimeType,
            'fileExtension' => $payload->extension,
            'fileSize' => $s3Metadata->size,
            'fileName' => $payload->fileName,
            'mediaTypeRaw' => serialize($mediaType),
            'metaData' => $this->buildMetadata($s3Metadata->etag, $mimeType, $payload),
            'uploadedAt' => $media->getUploadedAt() ?? new \DateTime(),
        ];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->mediaRepository->update([$data], $context);
        });
    }

    private function dispatchFinalizeEvents(string $mediaId, string $path, string $mimeType, Context $context): void
    {
        $this->eventDispatcher->dispatch(new UpdateMediaPathEvent([$mediaId]));

        $mediaPathChanged = new MediaPathChangedEvent($context);
        $mediaPathChanged->mediaWithMimeType(mediaId: $mediaId, path: $path, mimeType: $mimeType);
        $this->eventDispatcher->dispatch($mediaPathChanged);

        $this->eventDispatcher->dispatch(new MediaUploadedEvent($mediaId, $context));

        $this->mediaFileCleanup->dispatchThumbnailGeneration($mediaId, $context);
    }

    private function deleteMediaEntity(string $mediaId, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId): void {
            $this->mediaRepository->delete([['id' => $mediaId]], $context);
        });
    }

    private function persistReplaceUploadedAt(string $mediaId, \DateTimeImmutable $uploadedAt, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId, $uploadedAt): void {
            $this->mediaRepository->update([
                ['id' => $mediaId, 'uploadedAt' => \DateTime::createFromImmutable($uploadedAt)],
            ], $context);
        });
    }

    private function findMedia(string $mediaId, Context $context): ?MediaEntity
    {
        return $this->mediaRepository->search(new Criteria([$mediaId]), $context)->getEntities()->first();
    }

    private function isFileNameTaken(string $mediaId, string $fileName, string $fileExtension, bool $isPrivate, Context $context): bool
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

            return true;
        }

        return false;
    }

    private function ensureFileNameIsUnique(string $mediaId, string $fileName, string $fileExtension, bool $isPrivate, Context $context): void
    {
        if ($this->isFileNameTaken($mediaId, $fileName, $fileExtension, $isPrivate, $context)) {
            throw MediaException::duplicatedMediaFileName($fileName, $fileExtension);
        }
    }

    private function validateExpectedPath(string $mediaId, PresignedUploadFinalizePayload $payload, MediaEntity $media): void
    {
        $uploadedAt = $media->getUploadedAt();

        $location = new MediaLocationStruct(
            $mediaId,
            $payload->extension,
            $payload->fileName,
            $uploadedAt instanceof \DateTime ? \DateTimeImmutable::createFromMutable($uploadedAt) : $uploadedAt,
        );

        $paths = $this->mediaPathStrategy->generate([$location]);
        $expectedPath = $paths[$mediaId] ?? null;

        if ($expectedPath === null || $expectedPath !== $payload->path) {
            $this->logger->error('Could not verify presigned upload for media "{mediaId}": path mismatch (expected "{expectedPath}", got "{submittedPath}")', [
                'mediaId' => $mediaId,
                'expectedPath' => $expectedPath,
                'submittedPath' => $payload->path,
                'uploadedAt' => $uploadedAt?->format(\DateTimeInterface::ATOM),
            ]);

            throw MediaException::presignedUploadFinalizeFailed($mediaId);
        }
    }

    private function detectMediaType(string $mimeType, string $extension): MediaType
    {
        $mediaFile = new MediaFile('', $mimeType, $extension, 0);

        try {
            return $this->typeDetector->detect($mediaFile);
        } catch (\Throwable) {
            // Fall back to basic type from MIME prefix.
            $mime = explode('/', $mimeType);

            return match ($mime[0]) {
                'image' => new ImageType(),
                'video' => new VideoType(),
                'audio' => new AudioType(),
                default => new BinaryType(),
            };
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildMetadata(?string $fileHash, string $mimeType, PresignedUploadFinalizePayload $payload): ?array
    {
        $metaData = [];

        if ($fileHash !== null) {
            $metaData['hash'] = $fileHash;
        }

        if ($payload->width !== null && $payload->height !== null) {
            $metaData['width'] = $payload->width;
            $metaData['height'] = $payload->height;
        }

        $imageType = $this->resolveImageType($mimeType);
        if ($imageType !== null) {
            $metaData['type'] = $imageType;
        }

        return $metaData ?: null;
    }

    private function resolveImageType(string $mimeType): ?int
    {
        return match ($mimeType) {
            'image/gif' => \IMAGETYPE_GIF,
            'image/jpeg' => \IMAGETYPE_JPEG,
            'image/png' => \IMAGETYPE_PNG,
            'image/bmp', 'image/x-ms-bmp' => \IMAGETYPE_BMP,
            'image/tiff' => \IMAGETYPE_TIFF_II,
            'image/webp' => \IMAGETYPE_WEBP,
            'image/avif' => \IMAGETYPE_AVIF,
            default => null,
        };
    }
}
