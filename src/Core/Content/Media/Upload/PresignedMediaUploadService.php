<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
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
     * @param array<string> $allowedExtensions
     * @param list<string> $privateAllowedExtensions
     */
    public function __construct(
        private EntityRepository $mediaRepository,
        private PresignedUrlGeneratorInterface $presignedUrlGenerator,
        private EventDispatcherInterface $eventDispatcher,
        private TypeDetector $typeDetector,
        private MediaFileCleanupService $mediaFileCleanup,
        private array $allowedExtensions,
        private array $privateAllowedExtensions,
        private AbstractMediaPathStrategy $mediaPathStrategy,
        private LoggerInterface $logger,
    ) {
        $this->fileNameValidator = new FileNameValidator();
    }

    /**
     * @return array{mediaId: string, url: string, path: string, expiresAt: string, isDuplicate: bool}
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

        if ($payload->mediaId !== null) {
            $media = $this->findMedia($payload->mediaId, $context);

            if ($media === null) {
                throw MediaException::mediaNotFound($payload->mediaId);
            }

            $this->validateFileExtension($extension, $media->isPrivate(), $payload->mediaId);

            $mediaId = $payload->mediaId;
            $uploadedAt = new \DateTimeImmutable();
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId, $uploadedAt): void {
                $this->mediaRepository->update([
                    ['id' => $mediaId, 'uploadedAt' => \DateTime::createFromImmutable($uploadedAt)],
                ], $context);
            });
        } else {
            $this->validateFileExtension($extension, $payload->private);

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

        $isDuplicate = false;
        if (!$isReplace) {
            $isDuplicate = $this->isFileNameTaken($mediaId, $fileName, $extension, $payload->private, $context);
        }

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
            'isDuplicate' => $isDuplicate,
        ];
    }

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

        try {
            $this->validateFileExtension($payload->extension, $media->isPrivate(), $mediaId);
            $this->validateExpectedPath($mediaId, $payload, $media);

            if (!$isReplace) {
                $this->ensureFileNameIsUnique($mediaId, $payload->fileName, $payload->extension, $media->isPrivate(), $context);
            }
            $s3Metadata = $this->presignedUrlGenerator->getFileMetadata($payload->path);

            if ($s3Metadata === null) {
                $this->logger->error('Could not verify presigned upload for media "{mediaId}": file not found on storage at path "{path}"', [
                    'mediaId' => $mediaId,
                    'path' => $payload->path,
                ]);

                throw MediaException::presignedUploadFinalizeFailed($mediaId);
            }

            if ($isReplace) {
                $oldPath = $media->getPath();

                if ($oldPath !== '' && $oldPath !== $payload->path) {
                    $this->mediaFileCleanup->removeOldMediaData($media, $context);
                } else {
                    $this->mediaFileCleanup->deleteThumbnails($media, $context);
                }
            }

            $fileSize = $s3Metadata->size;
            $fileHash = $s3Metadata->etag;

            $mediaType = $this->detectMediaType($payload->mimeType, $payload->extension);
            $metaData = $this->buildMetadata($fileHash, $payload);

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

            $mediaPathChanged = new MediaPathChangedEvent($context);
            $mediaPathChanged->mediaWithMimeType(mediaId: $mediaId, path: $payload->path, mimeType: $payload->mimeType);
            $this->eventDispatcher->dispatch($mediaPathChanged);

            $this->eventDispatcher->dispatch(new MediaUploadedEvent($mediaId, $context));

            $this->mediaFileCleanup->dispatchThumbnailGeneration($mediaId, $context);
        } catch (\Throwable $e) {
            $this->presignedUrlGenerator->deleteFromStorage($payload->path);

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

    private function validateFileExtension(string $extension, bool $isPrivate, string $mediaId = ''): void
    {
        $event = new MediaFileExtensionWhitelistEvent($isPrivate ? $this->privateAllowedExtensions : $this->allowedExtensions);
        $this->eventDispatcher->dispatch($event);

        $fileExtension = mb_strtolower($extension);

        foreach ($event->getWhitelist() as $allowed) {
            if ($fileExtension === mb_strtolower((string) $allowed)) {
                return;
            }
        }

        throw MediaException::fileExtensionNotSupported($mediaId, $fileExtension);
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
    private function buildMetadata(?string $fileHash, PresignedUploadFinalizePayload $payload): ?array
    {
        $metaData = [];

        if ($fileHash !== null) {
            $metaData['hash'] = $fileHash;
        }

        if ($payload->width !== null && $payload->height !== null) {
            $metaData['width'] = $payload->width;
            $metaData['height'] = $payload->height;
        }

        if ($payload->mimeType !== null) {
            $imageType = $this->resolveImageType($payload->mimeType);
            if ($imageType !== null) {
                $metaData['type'] = $imageType;
            }
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
