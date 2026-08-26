<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Content\Media\Upload\FileMetadataResult;
use Shopware\Core\Content\Media\Upload\MediaFileCleanupService;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionValidator;
use Shopware\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopware\Core\Content\Media\Upload\PresignedUploadFinalizePayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadPreparePayload;
use Shopware\Core\Content\Media\Upload\PresignedUrlGeneratorInterface;
use Shopware\Core\Content\Media\Upload\PresignedUrlResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PresignedMediaUploadService::class)]
class PresignedMediaUploadServiceTest extends TestCase
{
    private PresignedUrlGeneratorInterface&MockObject $presignedUrlGenerator;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private AbstractMediaPathStrategy&Stub $mediaPathStrategy;

    private MediaFileCleanupService&MockObject $mediaFileCleanup;

    private MediaFileExtensionValidator&MockObject $extensionValidator;

    protected function setUp(): void
    {
        $this->presignedUrlGenerator = $this->createMock(PresignedUrlGeneratorInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mediaPathStrategy = static::createStub(AbstractMediaPathStrategy::class);
        $this->mediaFileCleanup = $this->createMock(MediaFileCleanupService::class);
        $this->extensionValidator = $this->createMock(MediaFileExtensionValidator::class);

        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static fn (object $event) => $event,
        );
    }

    public function testPrepareCreatesMediaAndReturnsPresignedUrl(): void
    {
        // isFileNameTaken search — no duplicates.
        [$repo, $service] = $this->createService([new MediaCollection()]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $context = Context::createDefaultContext();
        $expiresAt = new \DateTimeImmutable('+5 minutes');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                static::callback(fn (MediaLocationStruct $location): bool => $location->fileName === 'test-file' && $location->extension === 'jpg' && $location->uploadedAt !== null),
                'image/jpeg',
                false
            )
            ->willReturn(new PresignedUrlResult(
                url: 'https://s3.example.com/presigned-url',
                path: 'media/ab/cd/test-file.jpg',
                expiresAt: $expiresAt,
            ));

        $payload = new PresignedUploadPreparePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        $result = $service->prepare($payload, $context);

        static::assertCount(1, $repo->creates);
        $created = $repo->creates[0][0];
        static::assertArrayHasKey('id', $created);
        static::assertFalse($created['private']);
        static::assertInstanceOf(\DateTime::class, $created['uploadedAt']);

        static::assertNotEmpty($result->mediaId);
        static::assertSame('https://s3.example.com/presigned-url', $result->url);
        static::assertSame('media/ab/cd/test-file.jpg', $result->path);
        static::assertSame($expiresAt->format(\DateTimeInterface::ATOM), $result->expiresAt);
        static::assertFalse($result->isDuplicate);
    }

    public function testPrepareWithMediaFolderId(): void
    {
        [$repo, $service] = $this->createService([new MediaCollection()]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn(new PresignedUrlResult(
                url: 'https://s3.example.com/url',
                path: 'media/path.jpg',
                expiresAt: new \DateTimeImmutable('+5 minutes'),
            ));

        $payload = new PresignedUploadPreparePayload(
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            mediaFolderId: 'folder-123',
        );

        $service->prepare($payload, Context::createDefaultContext());

        static::assertCount(1, $repo->creates);
        static::assertSame('folder-123', $repo->creates[0][0]['mediaFolderId']);
    }

    public function testPrepareDeletesMediaOnGenerateFailure(): void
    {
        [$repo, $service] = $this->createService([new MediaCollection()]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('generate')
            ->willThrowException(MediaException::presignedUploadNotSupported());

        $this->expectException(MediaException::class);

        $payload = new PresignedUploadPreparePayload(
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        try {
            $service->prepare($payload, Context::createDefaultContext());
        } finally {
            static::assertCount(1, $repo->creates);
            static::assertCount(1, $repo->deletes);
            static::assertCount(0, $repo->updates);
        }
    }

    public function testPrepareReplaceDoesNotPersistUploadedAtWhenGenerateFails(): void
    {
        $mediaId = '0189b0a1-0000-0000-0000-0000000000aa';
        $media = $this->buildMedia($mediaId);

        // 1st search: findMedia (replace branch).
        [$repo, $service] = $this->createService([new MediaCollection([$media])]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('generate')
            ->willThrowException(MediaException::presignedUploadNotSupported());

        $this->expectException(MediaException::class);

        $payload = new PresignedUploadPreparePayload(
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            mediaId: $mediaId,
        );

        try {
            $service->prepare($payload, Context::createDefaultContext());
        } finally {
            // Invariant: a failed replace-prepare must leave the entity untouched. In particular
            // uploadedAt must not be persisted, otherwise the stored value would diverge from the
            // actual path column.
            static::assertCount(0, $repo->updates);
            static::assertCount(0, $repo->creates);
            static::assertCount(0, $repo->deletes);
        }
    }

    public function testPrepareReplacePersistsUploadedAtOnlyAfterPresignSucceeds(): void
    {
        $mediaId = '0189b0a1-0000-0000-0000-0000000000bb';
        $originalUploadedAt = new \DateTime('2024-01-01 00:00:00');

        $media = $this->buildMedia($mediaId);
        $media->setUploadedAt($originalUploadedAt);

        [$repo, $service] = $this->createService([new MediaCollection([$media])]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn(new PresignedUrlResult(
                url: 'https://s3.example.com/replace',
                path: 'media/ab/cd/replace.jpg',
                expiresAt: new \DateTimeImmutable('+5 minutes'),
            ));

        $payload = new PresignedUploadPreparePayload(
            fileName: 'replace',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            mediaId: $mediaId,
        );

        $service->prepare($payload, Context::createDefaultContext());

        static::assertCount(1, $repo->updates);
        $update = $repo->updates[0][0];
        static::assertSame($mediaId, $update['id']);
        static::assertInstanceOf(\DateTime::class, $update['uploadedAt']);
        static::assertGreaterThan($originalUploadedAt, $update['uploadedAt']);
    }

    public function testFinalizeVerifiesAndUpdatesMedia(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000001';
        $path = 'media/ab/cd/test-file.jpg';

        $media = $this->buildMedia($mediaId);

        // 1st search: findMediaWithThumbnails. 2nd search: ensureFileNameIsUnique (non-replace).
        [$repo, $service] = $this->createService([
            new MediaCollection([$media]),
            new MediaCollection(),
        ]);

        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => $path]);

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->with($path, false)
            ->willReturn(new FileMetadataResult(
                size: 12345,
                lastModified: new \DateTimeImmutable(),
                etag: 'd41d8cd98f00b204e9800998ecf8427e',
                contentType: 'image/jpeg',
            ));

        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch');
        $this->mediaFileCleanup->expects($this->once())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: $path,
            width: 1920,
            height: 1080,
        );

        $service->finalize($mediaId, $payload, $context);

        static::assertCount(1, $repo->updates);
        $update = $repo->updates[0][0];
        static::assertSame($mediaId, $update['id']);
        static::assertSame('image/jpeg', $update['mimeType']);
        static::assertSame('jpg', $update['fileExtension']);
        static::assertSame(12345, $update['fileSize']);
        static::assertSame('test-file', $update['fileName']);
        static::assertArrayHasKey('uploadedAt', $update);
        static::assertSame('d41d8cd98f00b204e9800998ecf8427e', $update['metaData']['hash']);
        static::assertSame(1920, $update['metaData']['width']);
        static::assertSame(1080, $update['metaData']['height']);
        static::assertSame(\IMAGETYPE_JPEG, $update['metaData']['type']);
    }

    public function testFinalizeStripsCharsetFromContentTypeBeforePersisting(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-00000000abcd';
        $path = 'media/ab/cd/umlauts.txt';

        $media = $this->buildMedia($mediaId);

        [$repo, $service] = $this->createService([
            new MediaCollection([$media]),
            new MediaCollection(),
        ]);

        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => $path]);

        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch');
        $this->mediaFileCleanup->expects($this->once())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        // S3 stores the canonical Content-Type incl. charset; the persisted entity mimeType must stay bare.
        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->with($path, false)
            ->willReturn(new FileMetadataResult(
                size: 42,
                lastModified: new \DateTimeImmutable(),
                etag: 'd41d8cd98f00b204e9800998ecf8427e',
                contentType: 'text/plain; charset=utf-8',
            ));

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'umlauts',
            extension: 'txt',
            mimeType: 'text/plain',
            path: $path,
        );

        $service->finalize($mediaId, $payload, $context);

        static::assertCount(1, $repo->updates);
        static::assertSame('text/plain', $repo->updates[0][0]['mimeType']);
    }

    public function testFinalizeRollsBackOrphanUploadOnDuplicateFileName(): void
    {
        // Two concurrent prepares for the same filename both pass isFileNameTaken (neither had a
        // file yet). The slower finalize is the "loser" of the race — ensureFileNameIsUnique must
        // throw AND the already-uploaded S3 object + the orphan media entity must be cleaned up.
        $mediaId = '0189b0a1-0000-0000-0000-00000000cccc';
        $path = 'media/ab/cd/collision.jpg';

        $media = $this->buildMedia($mediaId);

        $collidingMedia = $this->buildMedia('0189b0a1-0000-0000-0000-0000000000cc');
        $collidingMedia->assign([
            'path' => 'media/existing/collision.jpg',
            'fileName' => 'collision',
            'fileExtension' => 'jpg',
        ]);

        [$repo, $service] = $this->createService([
            // 1st search: findMediaWithThumbnails.
            new MediaCollection([$media]),
            // 2nd search: ensureFileNameIsUnique (the winner is already persisted with this name).
            new MediaCollection([$collidingMedia]),
        ]);

        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => $path]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        // Finalize never reaches verifyFileOnStorage/persistMediaData because uniqueness rejects first.
        $this->presignedUrlGenerator->expects($this->never())->method('getFileMetadata');

        // The last S3 object AND media entity must be cleaned up. Path has been validated, so
        // deleting on the submitted path is safe.
        $this->presignedUrlGenerator->expects($this->once())
            ->method('deleteFromStorage')
            ->with($path, false);

        $this->expectException(MediaException::class);

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'collision',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: $path,
        );

        try {
            $service->finalize($mediaId, $payload, Context::createDefaultContext());
        } finally {
            static::assertCount(1, $repo->deletes, 'orphan media entity must be removed on race loss');
            $deleted = $repo->deletes[0][0] ?? [];
            static::assertSame($mediaId, $deleted['id'] ?? null);
        }
    }

    public function testFinalizeThrowsWhenFileNotFoundInS3(): void
    {
        $mediaId = '0189b0a1-0000-0000-0000-000000000002';
        $path = 'media/ab/cd/test-file.jpg';

        $media = $this->buildMedia($mediaId);

        [, $service] = $this->createService([
            new MediaCollection([$media]),
            new MediaCollection(),
        ]);

        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => $path]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->with($path, false)
            ->willReturn(null);

        // Validation passed (path matches) — cleanup on the validated path is safe.
        $this->presignedUrlGenerator->expects($this->once())
            ->method('deleteFromStorage')
            ->with($path, false);

        $this->expectExceptionObject(MediaException::presignedUploadFinalizeFailed($mediaId));

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: $path,
        );

        $service->finalize($mediaId, $payload, Context::createDefaultContext());
    }

    public function testFinalizeThrowsOnPathMismatchWithoutDeletingSubmittedPath(): void
    {
        $mediaId = '0189b0a1-0000-0000-0000-000000000003';

        $media = $this->buildMedia($mediaId);

        [, $service] = $this->createService([new MediaCollection([$media])]);

        $this->mediaPathStrategy->method('generate')
            ->willReturn([$mediaId => 'media/ab/cd/test-file.jpg']);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        // Security invariant: the submitted path must not be touched when validation fails,
        // otherwise an attacker could trigger deletion of an arbitrary victim file by pointing
        // $payload->path at it.
        $this->presignedUrlGenerator->expects($this->never())->method('deleteFromStorage');
        $this->presignedUrlGenerator->expects($this->never())->method('getFileMetadata');

        $this->expectExceptionObject(MediaException::presignedUploadFinalizeFailed($mediaId));

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: 'media/tampered/path/evil.jpg',
        );

        $service->finalize($mediaId, $payload, Context::createDefaultContext());
    }

    public function testFinalizeThrowsOnDisallowedExtensionWithoutDeletingSubmittedPath(): void
    {
        $mediaId = '0189b0a1-0000-0000-0000-000000000004';

        $media = $this->buildMedia($mediaId);

        [, $service] = $this->createService([new MediaCollection([$media])]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');

        $this->extensionValidator->expects($this->once())
            ->method('validate')
            ->with('php', false, static::anything(), $mediaId)
            ->willThrowException(MediaException::fileExtensionNotSupported($mediaId, 'php'));

        // Security invariant: even if the attacker submits a valid victim path, a finalize with a
        // disallowed extension must not cause a storage deletion.
        $this->presignedUrlGenerator->expects($this->never())->method('deleteFromStorage');
        $this->presignedUrlGenerator->expects($this->never())->method('getFileMetadata');

        $this->expectExceptionObject(MediaException::fileExtensionNotSupported($mediaId, 'php'));

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'malicious',
            extension: 'php',
            mimeType: 'application/x-php',
            path: 'media/ab/cd/victims-legit-file.jpg',
        );

        $service->finalize($mediaId, $payload, Context::createDefaultContext());
    }

    public function testFinalizeWithoutDimensionsStoresOnlyHash(): void
    {
        $mediaId = '0189b0a1-0000-0000-0000-000000000005';
        $path = 'media/ab/cd/video.mp4';

        $media = $this->buildMedia($mediaId);

        [$repo, $service] = $this->createService([
            new MediaCollection([$media]),
            new MediaCollection(),
        ]);

        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => $path]);

        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch');
        $this->mediaFileCleanup->expects($this->once())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->willReturn(new FileMetadataResult(
                size: 50_000_000,
                lastModified: new \DateTimeImmutable(),
                etag: 'abc123def456',
                contentType: 'video/mp4',
            ));

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'video',
            extension: 'mp4',
            mimeType: 'video/mp4',
            path: $path,
        );

        $service->finalize($mediaId, $payload, Context::createDefaultContext());

        static::assertCount(1, $repo->updates);
        $update = $repo->updates[0][0];
        static::assertSame(50_000_000, $update['fileSize']);
        static::assertSame(['hash' => 'abc123def456'], $update['metaData']);
    }

    public function testIsAvailableDelegatesToGenerator(): void
    {
        [, $service] = $this->createService();

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->never())->method('validate');

        $this->presignedUrlGenerator->expects($this->exactly(2))
            ->method('isSupported')
            ->willReturn(true, false);

        static::assertTrue($service->isAvailable());
        static::assertFalse($service->isAvailable());
    }

    public function testFinalizeReplaceRemovesOldMediaData(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000010';
        $oldPath = 'media/ab/cd/old-file.png';
        $newPath = 'media/ef/gh/test-file.jpg';

        $media = $this->buildMedia($mediaId);
        $media->assign(['path' => $oldPath, 'fileName' => 'old-file', 'fileExtension' => 'png']);

        [$repo, $service] = $this->createService([new MediaCollection([$media])]);

        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => $newPath]);

        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->with($newPath, false)
            ->willReturn(new FileMetadataResult(
                size: 5000,
                lastModified: new \DateTimeImmutable(),
                etag: 'replace-hash-123',
                contentType: 'image/jpeg',
            ));

        $this->mediaFileCleanup->expects($this->once())
            ->method('removeOldMediaData')
            ->with($media, $context);

        $this->mediaFileCleanup->expects($this->once())
            ->method('dispatchThumbnailGeneration')
            ->with($mediaId, $context);

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: $newPath,
            width: 800,
            height: 600,
        );

        $service->finalize($mediaId, $payload, $context);

        static::assertCount(1, $repo->updates);
        $update = $repo->updates[0][0];
        static::assertSame($mediaId, $update['id']);
        static::assertSame('image/jpeg', $update['mimeType']);
        static::assertSame('jpg', $update['fileExtension']);
        static::assertSame(5000, $update['fileSize']);
        static::assertSame('replace-hash-123', $update['metaData']['hash']);
    }

    public function testFinalizeReplaceSamePathDeletesThumbnails(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000011';
        $path = 'media/ab/cd/same-file.jpg';

        $media = $this->buildMedia($mediaId);
        $media->assign(['path' => $path, 'fileName' => 'same-file', 'fileExtension' => 'jpg']);

        [, $service] = $this->createService([new MediaCollection([$media])]);

        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => $path]);

        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->willReturn(new FileMetadataResult(
                size: 3000,
                lastModified: new \DateTimeImmutable(),
                etag: 'same-path-hash',
                contentType: 'image/jpeg',
            ));

        $this->mediaFileCleanup->expects($this->never())->method('removeOldMediaData');
        $this->mediaFileCleanup->expects($this->once())
            ->method('deleteThumbnails')
            ->with($media, $context);

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'same-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: $path,
            width: 640,
            height: 480,
        );

        $service->finalize($mediaId, $payload, $context);
    }

    public function testPreparePrivateUploadIsSignedAsPrivate(): void
    {
        // isFileNameTaken search — no duplicates.
        [$repo, $service] = $this->createService([new MediaCollection()]);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->mediaFileCleanup->expects($this->never())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('generate')
            ->with(static::anything(), 'application/pdf', true)
            ->willReturn(new PresignedUrlResult(
                url: 'https://private-bucket.s3.example.com/presigned-url',
                path: 'media/ab/cd/secret.pdf',
                expiresAt: new \DateTimeImmutable('+5 minutes'),
            ));

        $payload = new PresignedUploadPreparePayload(
            fileName: 'secret',
            extension: 'pdf',
            mimeType: 'application/pdf',
            private: true,
        );

        $result = $service->prepare($payload, Context::createDefaultContext());

        static::assertTrue($repo->creates[0][0]['private']);
        static::assertSame('https://private-bucket.s3.example.com/presigned-url', $result->url);
    }

    public function testFinalizePrivateUploadIsVerifiedAsPrivate(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-0000000000dd';
        $path = 'media/ab/cd/secret.pdf';

        $media = $this->buildMedia($mediaId, true);

        // 1st search: findMediaWithThumbnails. 2nd search: ensureFileNameIsUnique (non-replace).
        [, $service] = $this->createService([
            new MediaCollection([$media]),
            new MediaCollection(),
        ]);

        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => $path]);

        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch');
        $this->mediaFileCleanup->expects($this->once())->method('dispatchThumbnailGeneration');
        $this->extensionValidator->expects($this->once())->method('validate');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->with($path, true)
            ->willReturn(new FileMetadataResult(
                size: 2048,
                lastModified: new \DateTimeImmutable(),
                etag: 'd41d8cd98f00b204e9800998ecf8427e',
                contentType: 'application/pdf',
            ));

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'secret',
            extension: 'pdf',
            mimeType: 'application/pdf',
            path: $path,
        );

        $service->finalize($mediaId, $payload, $context);
    }

    /**
     * @param list<MediaCollection> $searches
     *
     * @return array{StaticEntityRepository<MediaCollection>, PresignedMediaUploadService}
     */
    private function createService(array $searches = []): array
    {
        $repo = new StaticEntityRepository($searches);

        $service = new PresignedMediaUploadService(
            $repo,
            $this->presignedUrlGenerator,
            $this->eventDispatcher,
            static::createStub(TypeDetector::class),
            $this->mediaFileCleanup,
            $this->extensionValidator,
            $this->mediaPathStrategy,
            new NullLogger(),
            new NativeClock()
        );

        return [$repo, $service];
    }

    private function buildMedia(string $mediaId, bool $private = false): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setUploadedAt(new \DateTime());
        $media->setPrivate($private);
        $media->setThumbnails(new MediaThumbnailCollection());

        return $media;
    }
}
