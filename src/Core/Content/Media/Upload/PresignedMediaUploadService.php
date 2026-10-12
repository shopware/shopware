<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Event\MediaPathChangedEvent;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileInfoHelper;
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
use Symfony\Component\Mime\MimeTypes;
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
        private ClockInterface $clock,
        private PresignedUploadTokenSigner $tokenSigner,
    ) {
        $this->fileNameValidator = new FileNameValidator();
    }

    public function prepare(
        PresignedUploadPreparePayload $payload,
        Context $context,
    ): PresignedUploadPrepareResult {
        $this->fileNameValidator->validateFileName($payload->fileName);

        ['mediaId' => $mediaId, 'uploadedAt' => $uploadedAt, 'private' => $isPrivate] = $this->resolveMediaForPrepare($payload, $context);

        $isReplace = $payload->mediaId !== null;

        $isDuplicate = false;
        if (!$isReplace) {
            $isDuplicate = $this->isFileNameTaken($mediaId, $payload->fileName, $payload->extension, $payload->private, $context);
        }

        try {
            $result = $this->generatePresignedUrl($mediaId, $payload, $uploadedAt, $isPrivate);
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
        $isPrivate = $media->isPrivate();

        $this->validateFinalizeRequest($mediaId, $payload, $media, $context);

        try {
            if (!$isReplace) {
                $this->ensureFileNameIsUnique($mediaId, $payload->fileName, $payload->extension, $isPrivate, $context);
            }

            $s3Metadata = $this->verifyFileOnStorage($mediaId, $payload->path, $isPrivate);

            if ($isReplace) {
                $this->cleanupOldMediaData($media, $payload->path, $context);
            }

            // The remote-storage object stores the canonical Content-Type incl. `charset` (see PresignedUploadUrlGenerator);
            // strip any parameters so the persisted entity mimeType stays bare for media-type/extension detection.
            $mimeType = FileInfoHelper::stripParameters($s3Metadata->contentType ?? $payload->mimeType);

            $this->persistMediaData($mediaId, $payload, $s3Metadata, $mimeType, $media, $context);
            $this->dispatchFinalizeEvents($mediaId, $payload->path, $mimeType, $context);
        } catch (\Throwable $e) {
            if (!$isReplace) {
                $this->presignedUrlGenerator->deleteFromStorage($payload->path, $isPrivate);
                $this->deleteMediaEntity($mediaId, $context);
            }

            throw $e;
        }
    }

    public function isAvailable(): bool
    {
        return $this->presignedUrlGenerator->isSupported();
    }

    public function requestUpload(MediaUploadParameters $uploadParameters, Context $context): PresignedUploadTicket
    {
        if ($uploadParameters->fileName === null || $uploadParameters->fileName === '') {
            throw MediaException::emptyMediaFilename();
        }

        if ($uploadParameters->deduplicate === true) {
            throw MediaException::presignedUploadDeduplicationNotSupported();
        }

        $isReplace = $uploadParameters->id !== null;
        $this->assertWritePrivilege($isReplace, $context);

        $fileName = $uploadParameters->getFileNameWithoutExtension();
        $extension = $uploadParameters->getFileNameExtension();
        $this->fileNameValidator->validateFileName($fileName);

        $mediaId = $uploadParameters->id ?? Uuid::randomHex();

        if ($isReplace) {
            $isPrivate = $this->findMediaToReplace($mediaId, $context)->isPrivate();
            $this->extensionValidator->validate($extension, $isPrivate, $context, $mediaId);
        } else {
            $isPrivate = $uploadParameters->private ?? false;
            $this->extensionValidator->validate($extension, $isPrivate, $context);
        }

        $stagingUpload = $this->presignedUrlGenerator->generateStagingUpload($extension, $isPrivate);

        $token = new PresignedUploadToken(
            mediaId: $mediaId,
            stagingKey: $stagingUpload->path,
            fileName: $fileName,
            extension: $extension,
            mimeType: $this->resolveContentType($extension, $uploadParameters->mimeType),
            private: $isPrivate,
            mediaFolderId: $uploadParameters->mediaFolderId,
            isReplace: $isReplace,
            requestedBy: $this->resolveRequester($context),
            expiresAt: $stagingUpload->expiresAt,
        );

        return new PresignedUploadTicket(
            mediaId: $mediaId,
            uploadToken: $this->tokenSigner->sign($token),
            method: $stagingUpload->method,
            url: $stagingUpload->url,
            headers: $stagingUpload->headers,
            expiresAt: $stagingUpload->expiresAt->format(\DateTimeInterface::ATOM),
        );
    }

    public function confirmUpload(PresignedUploadConfirmPayload $payload, Context $context): string
    {
        $token = $this->tokenSigner->verify($payload->uploadToken, $this->clock->now());

        if ($token->requestedBy !== $this->resolveRequester($context)) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        $this->assertWritePrivilege($token->isReplace, $context);

        if ($token->isReplace) {
            return $this->confirmReplace($token, $payload, $context);
        }

        return $this->confirmCreate($token, $payload, $context);
    }

    private function confirmCreate(PresignedUploadToken $token, PresignedUploadConfirmPayload $payload, Context $context): string
    {
        if ($this->findMediaInSystemScope($token->mediaId, $context) !== null) {
            return $token->mediaId;
        }

        $verifiedStagedFile = $this->verifyFileOnStorage($token->mediaId, $token->stagingKey, $token->private);
        $mediaPath = null;
        $isMediaPersisted = false;

        try {
            $this->ensureFileNameIsUnique($token->mediaId, $token->fileName, $token->extension, $token->private, $context);

            $uploadedAt = $this->clock->now();
            $mediaPath = $this->promoteStagedFile($token, $verifiedStagedFile, $uploadedAt, null, $context);
            $this->persistConfirmedMedia($token, $payload, $verifiedStagedFile, $uploadedAt, $context);
            $isMediaPersisted = true;
            $this->dispatchFinalizeEvents($token->mediaId, $mediaPath, $token->mimeType, $context);

            return $token->mediaId;
        } catch (\Throwable $e) {
            $this->rollBackCreate($token, $mediaPath, $isMediaPersisted, $context);

            throw $e;
        }
    }

    private function confirmReplace(PresignedUploadToken $token, PresignedUploadConfirmPayload $payload, Context $context): string
    {
        $mediaToReplace = $this->findMediaWithThumbnails($token->mediaId, $context);
        $verifiedStagedFile = $this->verifyFileOnStorage($token->mediaId, $token->stagingKey, $token->private);
        $mediaPath = null;
        $isMediaPersisted = false;

        try {
            $uploadedAt = $this->clock->now();
            $mediaPath = $this->promoteStagedFile($token, $verifiedStagedFile, $uploadedAt, $mediaToReplace->getPath(), $context);
            $this->cleanupOldMediaData($mediaToReplace, $mediaPath, $context);

            $this->persistConfirmedMedia($token, $payload, $verifiedStagedFile, $uploadedAt, $context);
            $isMediaPersisted = true;
            $this->dispatchFinalizeEvents($token->mediaId, $mediaPath, $token->mimeType, $context);

            return $token->mediaId;
        } catch (\Throwable $e) {
            if ($mediaPath === null) {
                $this->presignedUrlGenerator->deleteFromStorage($token->stagingKey, $token->private);
            } elseif (!$isMediaPersisted && $mediaPath !== $mediaToReplace->getPath()) {
                $this->presignedUrlGenerator->deleteFromStorage($mediaPath, $token->private);
            }

            throw $e;
        }
    }

    private function promoteStagedFile(
        PresignedUploadToken $token,
        FileMetadataResult $verifiedStagedFile,
        \DateTimeImmutable $uploadedAt,
        ?string $currentPath,
        Context $context,
    ): string {
        $mediaLocation = new MediaLocationStruct($token->mediaId, $token->extension, $token->fileName, $uploadedAt);
        $mediaPath = $this->mediaPathStrategy->generate([$mediaLocation])[$token->mediaId]
            ?? throw MediaException::strategyNotFound($this->mediaPathStrategy->name());

        $isOwnPath = $mediaPath === $currentPath;
        if (!$isOwnPath && $this->isPathTaken($mediaPath, $token, $context)) {
            throw MediaException::duplicatedMediaFileName($token->fileName, $token->extension);
        }

        if ($verifiedStagedFile->etag === null
            || !$this->presignedUrlGenerator->promote($token->stagingKey, $mediaPath, $token->private, $token->mimeType, $verifiedStagedFile->etag, $isOwnPath)
        ) {
            throw MediaException::presignedUploadFinalizeFailed($token->mediaId);
        }

        return $mediaPath;
    }

    private function isPathTaken(string $mediaPath, PresignedUploadToken $token, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('path', $mediaPath),
            new EqualsFilter('private', $token->private),
            new NotEqualsFilter('id', $token->mediaId),
        );

        return $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $context): bool => $this->mediaRepository->searchIds($criteria, $context)->firstId() !== null
        );
    }

    private function rollBackCreate(PresignedUploadToken $token, ?string $mediaPath, bool $isMediaPersisted, Context $context): void
    {
        if ($mediaPath === null) {
            $this->presignedUrlGenerator->deleteFromStorage($token->stagingKey, $token->private);

            return;
        }

        $this->presignedUrlGenerator->deleteFromStorage($mediaPath, $token->private);

        // Deletes in system scope without `media:delete`, so an entity that existed before this call must survive.
        if ($isMediaPersisted) {
            $this->deleteMediaEntity($token->mediaId, $context);
        }
    }

    private function resolveRequester(Context $context): ?string
    {
        $contextSource = $context->getSource();
        if (!$contextSource instanceof AdminApiSource) {
            return null;
        }

        if ($contextSource->getUserId() !== null) {
            return 'user:' . $contextSource->getUserId();
        }

        return $contextSource->getIntegrationId() !== null ? 'integration:' . $contextSource->getIntegrationId() : null;
    }

    private function findMediaToReplace(string $mediaId, Context $context): MediaEntity
    {
        // A provided id is only a replace target, so an unknown id must not create media at a caller-chosen id.
        return $this->findMedia($mediaId, $context) ?? throw MediaException::mediaNotFound($mediaId);
    }

    /**
     * @return array{mediaId: string, uploadedAt: \DateTimeImmutable, private: bool}
     */
    private function resolveMediaForPrepare(PresignedUploadPreparePayload $payload, Context $context): array
    {
        if ($payload->mediaId !== null) {
            $media = $this->findMedia($payload->mediaId, $context);

            if ($media === null) {
                throw MediaException::mediaNotFound($payload->mediaId);
            }

            $this->extensionValidator->validate($payload->extension, $media->isPrivate(), $context, $payload->mediaId);

            return ['mediaId' => $payload->mediaId, 'uploadedAt' => $this->clock->now(), 'private' => $media->isPrivate()];
        }

        $this->extensionValidator->validate($payload->extension, $payload->private, $context);

        $mediaId = Uuid::randomHex();
        $uploadedAt = $this->clock->now();

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

        return ['mediaId' => $mediaId, 'uploadedAt' => $uploadedAt, 'private' => $payload->private];
    }

    private function generatePresignedUrl(string $mediaId, PresignedUploadPreparePayload $payload, \DateTimeImmutable $uploadedAt, bool $private): PresignedUrlResult
    {
        $location = new MediaLocationStruct(
            $mediaId,
            $payload->extension,
            $payload->fileName,
            $uploadedAt,
        );

        return $this->presignedUrlGenerator->generate($location, $payload->mimeType, $private);
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

    private function verifyFileOnStorage(string $mediaId, string $path, bool $private): FileMetadataResult
    {
        $s3Metadata = $this->presignedUrlGenerator->getFileMetadata($path, $private);

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
            'metaData' => $this->buildMetadata($s3Metadata->etag, $mimeType, $payload->width, $payload->height),
            'uploadedAt' => $media->getUploadedAt() ?? $this->clock->now(),
        ];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->mediaRepository->update([$data], $context);
        });
    }

    private function persistConfirmedMedia(
        PresignedUploadToken $token,
        PresignedUploadConfirmPayload $payload,
        FileMetadataResult $verifiedStagedFile,
        \DateTimeImmutable $uploadedAt,
        Context $context,
    ): void {
        $mediaPayload = [
            'id' => $token->mediaId,
            'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
            'mimeType' => $token->mimeType,
            'fileExtension' => $token->extension,
            'fileSize' => $verifiedStagedFile->size,
            'fileName' => $token->fileName,
            'mediaTypeRaw' => serialize($this->detectMediaType($token->mimeType, $token->extension)),
            'metaData' => $this->buildMetadata($verifiedStagedFile->etag, $token->mimeType, $payload->width, $payload->height),
            // The media path is derived from uploadedAt, so any other value would point the media at a missing object.
            'uploadedAt' => \DateTime::createFromImmutable($uploadedAt),
        ];

        if ($token->isReplace) {
            $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->mediaRepository->update([$mediaPayload], $context));

            return;
        }

        $mediaPayload['private'] = $token->private;
        $mediaPayload['mediaFolderId'] = $token->mediaFolderId;

        $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->mediaRepository->create([$mediaPayload], $context));
    }

    private function assertWritePrivilege(bool $isReplace, Context $context): void
    {
        $privilege = $isReplace ? 'media:update' : 'media:create';

        if (!$context->isAllowed($privilege)) {
            throw MediaException::missingPrivilege([$privilege]);
        }
    }

    private function resolveContentType(string $extension, ?string $requestedMimeType): string
    {
        $knownMimeTypes = MimeTypes::getDefault()->getMimeTypes(mb_strtolower($extension));
        $requestedContentType = $requestedMimeType !== null ? FileInfoHelper::stripParameters($requestedMimeType) : null;

        if ($requestedContentType !== null && \in_array($requestedContentType, $knownMimeTypes, true)) {
            return $requestedContentType;
        }

        return $knownMimeTypes[0] ?? 'application/octet-stream';
    }

    private function findMediaInSystemScope(string $mediaId, Context $context): ?MediaEntity
    {
        return $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $context): ?MediaEntity => $this->findMedia($mediaId, $context)
        );
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
        return $this->findMediaIdByFileName($mediaId, $fileName, $fileExtension, $isPrivate, $context) !== null;
    }

    private function findMediaIdByFileName(string $mediaId, string $fileName, string $fileExtension, bool $isPrivate, Context $context): ?string
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

            return $media->getId();
        }

        return null;
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
    private function buildMetadata(?string $fileHash, string $mimeType, ?int $width, ?int $height): ?array
    {
        $metaData = [];

        if ($fileHash !== null) {
            $metaData['hash'] = $fileHash;
        }

        if ($width !== null && $height !== null) {
            $metaData['width'] = $width;
            $metaData['height'] = $height;
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
