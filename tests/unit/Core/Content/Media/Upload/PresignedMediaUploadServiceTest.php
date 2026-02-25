<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\FileMetadataResult;
use Shopware\Core\Content\Media\Upload\MediaFileCleanupService;
use Shopware\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopware\Core\Content\Media\Upload\PresignedUploadFinalizePayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadPreparePayload;
use Shopware\Core\Content\Media\Upload\PresignedUrlGeneratorInterface;
use Shopware\Core\Content\Media\Upload\PresignedUrlResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PresignedMediaUploadService::class)]
class PresignedMediaUploadServiceTest extends TestCase
{
    /** @var EntityRepository<MediaCollection>&MockObject */
    private EntityRepository&MockObject $mediaRepository;

    private PresignedUrlGeneratorInterface&MockObject $presignedUrlGenerator;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private AbstractMediaPathStrategy&MockObject $mediaPathStrategy;

    private MediaFileCleanupService&MockObject $mediaFileCleanup;

    private PresignedMediaUploadService $service;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->createMock(EntityRepository::class);
        $this->presignedUrlGenerator = $this->createMock(PresignedUrlGeneratorInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mediaPathStrategy = $this->createMock(AbstractMediaPathStrategy::class);

        $typeDetector = $this->createMock(\Shopware\Core\Content\Media\TypeDetector\TypeDetector::class);
        $this->mediaFileCleanup = $this->createMock(MediaFileCleanupService::class);

        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) {
                return $event;
            }
        );

        $this->service = new PresignedMediaUploadService(
            $this->mediaRepository,
            $this->presignedUrlGenerator,
            $this->eventDispatcher,
            $typeDetector,
            $this->mediaFileCleanup,
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'mp3', 'pdf'],
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'mp3', 'pdf'],
            $this->mediaPathStrategy,
        );
    }

    public function testPrepareCreatesMediaAndReturnsPresignedUrl(): void
    {
        $context = Context::createDefaultContext();
        $expiresAt = new \DateTimeImmutable('+5 minutes');

        $this->mediaRepository->expects($this->once())
            ->method('create')
            ->with(
                static::callback(function (array $payload): bool {
                    static::assertCount(1, $payload);
                    static::assertArrayHasKey('id', $payload[0]);
                    static::assertFalse($payload[0]['private']);
                    static::assertArrayHasKey('uploadedAt', $payload[0]);
                    static::assertInstanceOf(\DateTime::class, $payload[0]['uploadedAt']);

                    return true;
                }),
                $context
            );

        $this->presignedUrlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                static::callback(fn (MediaLocationStruct $location): bool => $location->fileName === 'test-file' && $location->extension === 'jpg' && $location->uploadedAt !== null),
                'image/jpeg'
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

        $result = $this->service->prepare($payload, $context);

        static::assertArrayHasKey('mediaId', $result);
        static::assertSame('https://s3.example.com/presigned-url', $result['url']);
        static::assertSame('media/ab/cd/test-file.jpg', $result['path']);
        static::assertSame($expiresAt->format(\DateTimeInterface::ATOM), $result['expiresAt']);
        static::assertFalse($result['isDuplicate']);
    }

    public function testPrepareWithMediaFolderId(): void
    {
        $context = Context::createDefaultContext();

        $this->mediaRepository->expects($this->once())
            ->method('create')
            ->with(
                static::callback(function (array $payload): bool {
                    static::assertSame('folder-123', $payload[0]['mediaFolderId']);

                    return true;
                }),
                $context
            );

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

        $this->service->prepare($payload, $context);
    }

    public function testPrepareDeletesMediaOnGenerateFailure(): void
    {
        $context = Context::createDefaultContext();

        $this->mediaRepository->expects($this->once())->method('create');
        $this->mediaRepository->expects($this->once())->method('delete');

        $this->presignedUrlGenerator->expects($this->once())
            ->method('generate')
            ->willThrowException(MediaException::presignedUploadNotSupported());

        $this->expectException(MediaException::class);

        $payload = new PresignedUploadPreparePayload(
            fileName: 'test',
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        $this->service->prepare($payload, $context);
    }

    public function testPrepareThrowsOnMissingFileName(): void
    {
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('The parameter "fileName" is invalid.');

        $this->service->prepare(new PresignedUploadPreparePayload(extension: 'jpg', mimeType: 'image/jpeg'), $context);
    }

    public function testPrepareThrowsOnMissingExtension(): void
    {
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('The parameter "extension" is invalid.');

        $this->service->prepare(new PresignedUploadPreparePayload(fileName: 'test', mimeType: 'image/jpeg'), $context);
    }

    public function testPrepareThrowsOnMissingMimeType(): void
    {
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('The parameter "mimeType" is invalid.');

        $this->service->prepare(new PresignedUploadPreparePayload(fileName: 'test', extension: 'jpg'), $context);
    }

    public function testFinalizeVerifiesAndUpdatesMedia(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000001';
        $path = 'media/ab/cd/test-file.jpg';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setUploadedAt(new \DateTime());
        $media->setPrivate(false);
        $media->setThumbnails(new \Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection());

        $searchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $context
        );
        $this->mediaRepository->method('search')->willReturn($searchResult);

        $this->mediaPathStrategy->method('generate')
            ->willReturn([$mediaId => $path]);

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->with($path)
            ->willReturn(new FileMetadataResult(
                size: 12345,
                lastModified: new \DateTimeImmutable(),
                etag: 'd41d8cd98f00b204e9800998ecf8427e',
            ));

        $this->mediaRepository->expects($this->once())
            ->method('update')
            ->with(
                static::callback(function (array $data) use ($mediaId): bool {
                    static::assertSame($mediaId, $data[0]['id']);
                    static::assertSame('image/jpeg', $data[0]['mimeType']);
                    static::assertSame('jpg', $data[0]['fileExtension']);
                    static::assertSame(12345, $data[0]['fileSize']);
                    static::assertSame('test-file', $data[0]['fileName']);
                    static::assertArrayHasKey('uploadedAt', $data[0]);
                    static::assertSame('d41d8cd98f00b204e9800998ecf8427e', $data[0]['metaData']['hash']);
                    static::assertSame(1920, $data[0]['metaData']['width']);
                    static::assertSame(1080, $data[0]['metaData']['height']);
                    static::assertSame(\IMAGETYPE_JPEG, $data[0]['metaData']['type']);

                    return true;
                }),
                static::anything()
            );

        $this->eventDispatcher->expects($this->exactly(3))->method('dispatch');

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: $path,
            width: 1920,
            height: 1080,
        );

        $this->service->finalize($mediaId, $payload, $context);
    }

    public function testFinalizeThrowsWhenFileNotFoundInS3(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000002';
        $path = 'media/ab/cd/test-file.jpg';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setUploadedAt(new \DateTime());
        $media->setPrivate(false);
        $media->setThumbnails(new \Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection());

        $searchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $context
        );
        $this->mediaRepository->method('search')->willReturn($searchResult);

        $this->mediaPathStrategy->method('generate')
            ->willReturn([$mediaId => $path]);

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->with($path)
            ->willReturn(null);

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Could not verify uploaded file for media');

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: $path,
        );

        $this->service->finalize($mediaId, $payload, $context);
    }

    public function testFinalizeThrowsOnMissingFileName(): void
    {
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('The parameter "fileName" is invalid.');

        $this->service->finalize('media-id', new PresignedUploadFinalizePayload(extension: 'jpg', mimeType: 'image/jpeg', path: 'some/path'), $context);
    }

    public function testFinalizeThrowsOnMissingPath(): void
    {
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('The parameter "path" is invalid.');

        $this->service->finalize('media-id', new PresignedUploadFinalizePayload(fileName: 'test', extension: 'jpg', mimeType: 'image/jpeg'), $context);
    }

    public function testFinalizeThrowsOnPathMismatch(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000003';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setUploadedAt(new \DateTime());
        $media->setPrivate(false);
        $media->setThumbnails(new \Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection());

        $searchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $context
        );
        $this->mediaRepository->method('search')->willReturn($searchResult);

        $this->mediaPathStrategy->method('generate')
            ->willReturn([$mediaId => 'media/ab/cd/test-file.jpg']);

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Could not verify uploaded file for media');

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: 'media/tampered/path/evil.jpg',
        );

        $this->service->finalize($mediaId, $payload, $context);
    }

    public function testFinalizeThrowsOnDisallowedExtension(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000004';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setUploadedAt(new \DateTime());
        $media->setPrivate(false);
        $media->setThumbnails(new \Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection());

        $searchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $context
        );
        $this->mediaRepository->method('search')->willReturn($searchResult);

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('not supported');

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'malicious',
            extension: 'php',
            mimeType: 'application/x-php',
            path: 'media/ab/cd/malicious.php',
        );

        $this->service->finalize($mediaId, $payload, $context);
    }

    public function testFinalizeWithoutDimensionsStoresOnlyHash(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000005';
        $path = 'media/ab/cd/video.mp4';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setUploadedAt(new \DateTime());
        $media->setPrivate(false);
        $media->setThumbnails(new \Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection());

        $searchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $context
        );
        $this->mediaRepository->method('search')->willReturn($searchResult);

        $this->mediaPathStrategy->method('generate')
            ->willReturn([$mediaId => $path]);

        $this->presignedUrlGenerator->method('getFileMetadata')
            ->willReturn(new FileMetadataResult(
                size: 50_000_000,
                lastModified: new \DateTimeImmutable(),
                etag: 'abc123def456',
            ));

        $this->mediaRepository->expects($this->once())
            ->method('update')
            ->with(
                static::callback(function (array $data): bool {
                    static::assertSame(50_000_000, $data[0]['fileSize']);
                    static::assertSame(['hash' => 'abc123def456'], $data[0]['metaData']);

                    return true;
                }),
                static::anything()
            );

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'video',
            extension: 'mp4',
            mimeType: 'video/mp4',
            path: $path,
        );

        $this->service->finalize($mediaId, $payload, $context);
    }

    public function testIsSupportedDelegatesToGenerator(): void
    {
        $this->presignedUrlGenerator->expects($this->once())
            ->method('isSupported')
            ->willReturn(true);

        static::assertTrue($this->service->isSupported());
    }

    public function testIsEnabledDelegatesToGenerator(): void
    {
        $this->presignedUrlGenerator->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        static::assertFalse($this->service->isEnabled());
    }

    public function testFinalizeReplaceRemovesOldMediaData(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000010';
        $oldPath = 'media/ab/cd/old-file.png';
        $newPath = 'media/ef/gh/test-file.jpg';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setUploadedAt(new \DateTime());
        $media->setPrivate(false);
        $media->assign(['path' => $oldPath, 'fileName' => 'old-file', 'fileExtension' => 'png']);
        $media->setThumbnails(new \Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection());

        $searchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $context
        );
        $this->mediaRepository->method('search')->willReturn($searchResult);

        $this->mediaPathStrategy->method('generate')
            ->willReturn([$mediaId => $newPath]);

        $this->presignedUrlGenerator->expects($this->once())
            ->method('getFileMetadata')
            ->with($newPath)
            ->willReturn(new FileMetadataResult(
                size: 5000,
                lastModified: new \DateTimeImmutable(),
                etag: 'replace-hash-123',
            ));

        $this->mediaFileCleanup->expects($this->once())
            ->method('removeOldMediaData')
            ->with($media, $context);

        $this->mediaFileCleanup->expects($this->once())
            ->method('dispatchThumbnailGeneration')
            ->with($mediaId, $context);

        $this->mediaRepository->expects($this->once())
            ->method('update')
            ->with(
                static::callback(function (array $data) use ($mediaId): bool {
                    static::assertSame($mediaId, $data[0]['id']);
                    static::assertSame('image/jpeg', $data[0]['mimeType']);
                    static::assertSame('jpg', $data[0]['fileExtension']);
                    static::assertSame(5000, $data[0]['fileSize']);
                    static::assertSame('replace-hash-123', $data[0]['metaData']['hash']);

                    return true;
                }),
                static::anything()
            );

        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: $newPath,
            width: 800,
            height: 600,
        );

        $this->service->finalize($mediaId, $payload, $context);
    }

    public function testFinalizeReplaceSamePathDeletesThumbnails(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = '0189b0a1-0000-0000-0000-000000000011';
        $path = 'media/ab/cd/same-file.jpg';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setUploadedAt(new \DateTime());
        $media->setPrivate(false);
        $media->assign(['path' => $path, 'fileName' => 'same-file', 'fileExtension' => 'jpg']);
        $media->setThumbnails(new \Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection());

        $searchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $context
        );
        $this->mediaRepository->method('search')->willReturn($searchResult);

        $this->mediaPathStrategy->method('generate')
            ->willReturn([$mediaId => $path]);

        $this->presignedUrlGenerator->method('getFileMetadata')
            ->willReturn(new FileMetadataResult(
                size: 3000,
                lastModified: new \DateTimeImmutable(),
                etag: 'same-path-hash',
            ));

        $this->mediaFileCleanup->expects($this->never())
            ->method('removeOldMediaData');

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

        $this->service->finalize($mediaId, $payload, $context);
    }
}
