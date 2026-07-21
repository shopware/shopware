<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\FileUrlValidatorInterface;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailCollection;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailData;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaUploadService::class)]
class MediaUploadServiceTest extends TestCase
{
    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    /**
     * @var StaticEntityRepository<MediaThumbnailCollection>
     */
    private StaticEntityRepository $mediaThumbnailRepository;

    /**
     * @var StaticEntityRepository<MediaThumbnailSizeCollection>
     */
    private StaticEntityRepository $mediaThumbnailSizeRepository;

    private FileFetcher&Stub $fileFetcher;

    private FileSaver&Stub $fileSaver;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private HttpClientInterface&Stub $httpClient;

    private FileUrlValidatorInterface&Stub $fileUrlValidator;

    private MediaUploadService $mediaUploadService;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaRepository = new StaticEntityRepository([]);
        $this->mediaThumbnailRepository = new StaticEntityRepository([]);
        $this->mediaThumbnailSizeRepository = new StaticEntityRepository([]);
        $this->fileFetcher = static::createStub(FileFetcher::class);
        $this->fileSaver = static::createStub(FileSaver::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->httpClient = static::createStub(HttpClientInterface::class);
        $this->fileUrlValidator = static::createStub(FileUrlValidatorInterface::class);
        $this->fileUrlValidator->method('isValid')->willReturn(true);

        $this->mediaUploadService = $this->buildService();

        $this->context = Context::createDefaultContext();
    }

    public function testUploadFromLocalPath(): void
    {
        $filePath = __DIR__ . '/fixtures/test-image.jpg';
        $params = new MediaUploadParameters();

        (new Filesystem())->dumpFile($filePath, 'test content');

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia')
            ->with(
                static::isInstanceOf(MediaFile::class),
                static::isString(),
                static::isString(),
                $this->context
            );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MediaUploadedEvent::class));

        $service = $this->buildService(fileSaver: $fileSaver, eventDispatcher: $eventDispatcher);

        $result = $service->uploadFromLocalPath($filePath, $this->context, $params);

        static::assertIsString($result);
        static::assertTrue(Uuid::isValid($result));
        static::assertCount(1, $this->mediaRepository->creates);
        static::assertTrue(isset($this->mediaRepository->creates[0][0]['id']));
        static::assertTrue(isset($this->mediaRepository->creates[0][0]['private']));

        (new Filesystem())->remove($filePath);
    }

    public function testUploadFromLocalPathFileNotFound(): void
    {
        $filePath = '/non/existent/file.jpg';
        $params = new MediaUploadParameters();

        $this->expectException(MediaException::class);

        @$this->mediaUploadService->uploadFromLocalPath($filePath, $this->context, $params);
    }

    public function testUploadFromRequest(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'test.jpg',
            'image/jpeg',
            null,
            true
        );

        $request = new Request();
        $request->files->set('file', $uploadedFile);

        $params = new MediaUploadParameters();

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MediaUploadedEvent::class));

        $service = $this->buildService(fileSaver: $fileSaver, eventDispatcher: $eventDispatcher);

        $result = $service->uploadFromRequest($request, $this->context, $params);

        static::assertIsString($result);
        static::assertTrue(Uuid::isValid($result));
        static::assertCount(1, $this->mediaRepository->creates);

        (new Filesystem())->remove($tempFile);
    }

    public function testUploadFromRequestWithoutFile(): void
    {
        $request = new Request();
        $params = new MediaUploadParameters();

        $this->expectException(MediaException::class);

        $this->mediaUploadService->uploadFromRequest($request, $this->context, $params);
    }

    public function testUploadFromURL(): void
    {
        $url = 'https://example.com/image.jpg';
        $params = new MediaUploadParameters();

        $mediaFile = new MediaFile(
            'test.jpg',
            'image/jpeg',
            'jpg',
            1024,
            'test-hash'
        );

        $tmpDir = sys_get_temp_dir();
        static::assertNotEmpty($tmpDir);

        $fileFetcher = $this->createMock(FileFetcher::class);
        $fileFetcher
            ->expects($this->once())
            ->method('fetchFromURL')
            ->with(
                $url,
                static::stringContains($tmpDir)
            )
            ->willReturn($mediaFile);

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MediaUploadedEvent::class));

        $service = $this->buildService(fileFetcher: $fileFetcher, fileSaver: $fileSaver, eventDispatcher: $eventDispatcher);

        $result = $service->uploadFromURL($url, $this->context, $params);

        static::assertIsString($result);
        static::assertTrue(Uuid::isValid($result));
        static::assertCount(1, $this->mediaRepository->creates);
    }

    public function testLinkURL(): void
    {
        $url = 'https://example.com/image.jpg';
        $params = new MediaUploadParameters(
            fileName: 'test.jpg',
            mimeType: 'image/jpeg'
        );

        $response = static::createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([
            'content-length' => ['1024'],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('HEAD', $url, ['max_redirects' => 0])
            ->willReturn($response);

        $service = $this->buildService(httpClient: $httpClient);

        $result = $service->linkURL($url, $this->context, $params);

        static::assertIsString($result);
        static::assertTrue(Uuid::isValid($result));
        static::assertCount(1, $this->mediaRepository->creates);

        $createdMedia = $this->mediaRepository->creates[0][0];
        static::assertSame($url, $createdMedia['path']);
        static::assertSame(1024, $createdMedia['fileSize']);
        static::assertSame('test', $createdMedia['fileName']);
        static::assertSame('jpg', $createdMedia['fileExtension']);
        static::assertSame('image/jpeg', $createdMedia['mimeType']);
    }

    public function testLinkURLWithoutMimeType(): void
    {
        $url = 'https://example.com/image.jpg';
        $params = new MediaUploadParameters();

        $this->expectException(MediaException::class);

        $this->mediaUploadService->linkURL($url, $this->context, $params);
    }

    public function testLinkURLWithoutContentLength(): void
    {
        $url = 'https://example.com/image.jpg';
        $params = new MediaUploadParameters(
            fileName: 'test.jpg',
            mimeType: 'image/jpeg'
        );

        $response = static::createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('HEAD', $url, ['max_redirects' => 0])
            ->willReturn($response);

        $service = $this->buildService(httpClient: $httpClient);

        $this->expectException(MediaException::class);

        $service->linkURL($url, $this->context, $params);
    }

    public function testLinkURLWithDeduplication(): void
    {
        $url = 'https://example.com/image.jpg';
        $existingMediaId = Uuid::randomHex();
        $params = new MediaUploadParameters(
            fileName: 'test.jpg',
            mimeType: 'image/jpeg',
            deduplicate: true
        );

        // Setup the repository to return an existing media ID for deduplication
        $this->mediaRepository->addSearch([$existingMediaId]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $service = $this->buildService(httpClient: $httpClient);

        $result = $service->linkURL($url, $this->context, $params);

        static::assertSame($existingMediaId, $result);
        static::assertCount(0, $this->mediaRepository->creates);
    }

    public function testLinkURLWithAdminContext(): void
    {
        $url = 'https://example.com/image.jpg';
        $userId = Uuid::randomHex();
        $params = new MediaUploadParameters(
            fileName: 'test.jpg',
            mimeType: 'image/jpeg'
        );

        $adminSource = static::createStub(AdminApiSource::class);
        $adminSource->method('getUserId')->willReturn($userId);

        $adminContext = Context::createDefaultContext($adminSource);

        $response = static::createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([
            'content-length' => ['1024'],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $service = $this->buildService(httpClient: $httpClient);

        $service->linkURL($url, $adminContext, $params);

        static::assertCount(1, $this->mediaRepository->creates);
        static::assertSame($userId, $this->mediaRepository->creates[0][0]['userId']);
    }

    public function testUploadWithDeduplication(): void
    {
        $filePath = __DIR__ . '/fixtures/test-image.jpg';
        $existingMediaId = Uuid::randomHex();
        $params = new MediaUploadParameters(deduplicate: true);

        (new Filesystem())->dumpFile($filePath, 'test content');

        // Setup the repository to return an existing media ID for deduplication
        $this->mediaRepository->addSearch([$existingMediaId]);

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver->expects($this->never())->method('persistFileToMedia');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $result = $this->buildService(fileSaver: $fileSaver, eventDispatcher: $eventDispatcher)
            ->uploadFromLocalPath($filePath, $this->context, $params);

        static::assertSame($existingMediaId, $result);
        static::assertCount(0, $this->mediaRepository->creates);

        (new Filesystem())->remove($filePath);
    }

    public function testUploadWithErrorHandling(): void
    {
        $filePath = __DIR__ . '/fixtures/test-image.jpg';
        $params = new MediaUploadParameters();

        (new Filesystem())->dumpFile($filePath, 'test content');

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia')
            ->willThrowException(new \Exception('Upload failed'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $this->expectExceptionObject(new \Exception('Upload failed'));

        try {
            $this->buildService(fileSaver: $fileSaver, eventDispatcher: $eventDispatcher)
                ->uploadFromLocalPath($filePath, $this->context, $params);
        } finally {
            // Verify that the media was created and then deleted due to error
            static::assertCount(1, $this->mediaRepository->creates);
            static::assertCount(1, $this->mediaRepository->deletes);

            (new Filesystem())->remove($filePath);
        }
    }

    public function testUploadWithCustomParameters(): void
    {
        $filePath = __DIR__ . '/fixtures/test-image.jpg';
        $customId = Uuid::randomHex();
        $mediaFolderId = Uuid::randomHex();
        $params = new MediaUploadParameters(
            id: $customId,
            mediaFolderId: $mediaFolderId,
            private: true,
            fileName: 'custom-name.jpg'
        );

        // Create test file
        file_put_contents($filePath, 'test content');

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia')
            ->with(
                static::isInstanceOf(MediaFile::class),
                'custom-name',
                $customId,
                $this->context
            );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $result = $this->buildService(fileSaver: $fileSaver, eventDispatcher: $eventDispatcher)
            ->uploadFromLocalPath($filePath, $this->context, $params);

        static::assertSame($customId, $result);
        static::assertCount(1, $this->mediaRepository->creates);

        $createdMedia = $this->mediaRepository->creates[0][0];
        static::assertSame($customId, $createdMedia['id']);
        static::assertTrue($createdMedia['private']);
        static::assertSame($mediaFolderId, $createdMedia['mediaFolderId']);

        (new Filesystem())->remove($filePath);
    }

    public function testAddExternalThumbnailsToMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $collection = new ExternalThumbnailCollection([
            new ExternalThumbnailData('https://localhost:8000/thumb-200.jpg', 200, 200),
            new ExternalThumbnailData('https://localhost:8000/thumb-400.jpg', 400, 400),
        ]);

        $this->mediaThumbnailSizeRepository->addSearch([], []);

        $this->mediaUploadService->addExternalThumbnailsToMedia($mediaId, $collection, $this->context);

        static::assertCount(2, $this->mediaThumbnailSizeRepository->creates);
        static::assertCount(1, $this->mediaThumbnailSizeRepository->creates[0]);
        static::assertCount(1, $this->mediaThumbnailSizeRepository->creates[1]);

        static::assertCount(1, $this->mediaThumbnailRepository->creates);
        $createdThumbnails = $this->mediaThumbnailRepository->creates[0];
        static::assertCount(2, $createdThumbnails);

        static::assertSame($mediaId, $createdThumbnails[0]['mediaId']);
        static::assertSame('https://localhost:8000/thumb-200.jpg', $createdThumbnails[0]['path']);
        static::assertSame(200, $createdThumbnails[0]['width']);
        static::assertSame(200, $createdThumbnails[0]['height']);

        static::assertSame($mediaId, $createdThumbnails[1]['mediaId']);
        static::assertSame('https://localhost:8000/thumb-400.jpg', $createdThumbnails[1]['path']);
        static::assertSame(400, $createdThumbnails[1]['width']);
        static::assertSame(400, $createdThumbnails[1]['height']);
    }

    public function testDeleteAllExternalThumbnailsSkipsMediaWithZeroThumbnails(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaThumbnailRepository->addSearch([]);
        $this->mediaUploadService->deleteAllExternalThumbnails($mediaId, $this->context);

        static::assertCount(0, $this->mediaThumbnailRepository->deletes);
    }

    public function testLinkURLWithThumbnails(): void
    {
        $url = 'https://localhost:8000/image.jpg';
        $thumbnails = new ExternalThumbnailCollection([
            new ExternalThumbnailData('https://localhost:8000/thumb-200.jpg', 200, 200),
        ]);
        $params = new MediaUploadParameters(
            fileName: 'test.jpg',
            mimeType: 'image/jpeg',
            thumbnails: $thumbnails
        );

        $response = static::createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn(['content-length' => ['1024']]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('HEAD', $url, ['max_redirects' => 0])
            ->willReturn($response);

        $this->mediaThumbnailSizeRepository->addSearch([]);

        $result = $this->buildService(httpClient: $httpClient)->linkURL($url, $this->context, $params);

        static::assertIsString($result);
        static::assertCount(1, $this->mediaRepository->creates);
        static::assertCount(1, $this->mediaThumbnailRepository->creates);
        static::assertSame('https://localhost:8000/thumb-200.jpg', $this->mediaThumbnailRepository->creates[0][0]['path']);
    }

    public function testGetOrCreateThumbnailSizeReuseExistingSize(): void
    {
        $existingSizeId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $collection = new ExternalThumbnailCollection([
            new ExternalThumbnailData('https://localhost:8000/thumb.jpg', 300, 300),
        ]);

        $this->mediaThumbnailSizeRepository->addSearch([$existingSizeId]);

        $this->mediaUploadService->addExternalThumbnailsToMedia($mediaId, $collection, $this->context);

        static::assertCount(0, $this->mediaThumbnailSizeRepository->creates);

        static::assertCount(1, $this->mediaThumbnailRepository->creates);
        static::assertSame($existingSizeId, $this->mediaThumbnailRepository->creates[0][0]['mediaThumbnailSizeId']);
    }

    public function testDeleteAllExternalThumbnailsDeletesFoundThumbnails(): void
    {
        $mediaId = Uuid::randomHex();
        $thumbnailId1 = Uuid::randomHex();
        $thumbnailId2 = Uuid::randomHex();

        $this->mediaThumbnailRepository->addSearch([$thumbnailId1, $thumbnailId2]);

        $this->mediaUploadService->deleteAllExternalThumbnails($mediaId, $this->context);

        static::assertCount(1, $this->mediaThumbnailRepository->deletes);
        static::assertCount(2, $this->mediaThumbnailRepository->deletes[0]);
        static::assertSame(
            [$thumbnailId1, $thumbnailId2],
            array_column($this->mediaThumbnailRepository->deletes[0], 'id'),
        );
    }

    public function testValidateExternalUrlThrowsForInvalidFormat(): void
    {
        $this->expectExceptionObject(MediaException::invalidUrl('not-a-valid-url'));

        $this->mediaUploadService->assertValidExternalUrl('not-a-valid-url');
    }

    public function testValidateExternalUrlThrowsForPrivateIpUrl(): void
    {
        $validator = static::createStub(FileUrlValidatorInterface::class);
        $validator->method('isValid')->willReturn(false);

        $service = new MediaUploadService(
            $this->mediaRepository,
            $this->fileFetcher,
            $this->fileSaver,
            $this->eventDispatcher,
            $this->httpClient,
            $this->mediaThumbnailRepository,
            $this->mediaThumbnailSizeRepository,
            $validator,
        );

        $this->expectExceptionObject(MediaException::illegalUrl('http://10.0.0.1/image.jpg'));

        $service->assertValidExternalUrl('http://10.0.0.1/image.jpg');
    }

    /**
     * @deprecated tag:v6.8.0 - Remove this test when validateExternalUrl() is removed
     */
    public function testDeprecatedValidateExternalUrlThrowsForInvalidFormat(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->expectExceptionObject(MediaException::invalidUrl('not-a-valid-url'));

        MediaUploadService::validateExternalUrl('not-a-valid-url');
    }

    public function testLinkUrlRejectsPrivateIpUrl(): void
    {
        $validator = static::createStub(FileUrlValidatorInterface::class);
        $validator->method('isValid')->willReturn(false);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $service = $this->buildService(httpClient: $httpClient, fileUrlValidator: $validator);

        $this->expectExceptionObject(MediaException::illegalUrl('http://10.0.0.1/image.jpg'));

        $params = new MediaUploadParameters();
        $params->mimeType = 'image/jpeg';

        $service->linkURL('http://10.0.0.1/image.jpg', $this->context, $params);
    }

    public function testLinkUrlDisablesRedirects(): void
    {
        $capturedOptions = [];

        $response = static::createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn(['content-length' => ['12345']]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions, $response) {
                $capturedOptions = $options;

                return $response;
            });

        $params = new MediaUploadParameters();
        $params->mimeType = 'image/jpeg';

        $this->buildService(httpClient: $httpClient)->linkURL('https://example.com/image.jpg', $this->context, $params);

        static::assertArrayHasKey('max_redirects', $capturedOptions);
        static::assertSame(0, $capturedOptions['max_redirects']);
    }

    public function testLinkUrlSkipsIpValidationWhenValidationDisabled(): void
    {
        $fileUrlValidator = $this->createMock(FileUrlValidatorInterface::class);
        $fileUrlValidator->expects($this->never())->method('isValid');

        $response = static::createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn(['content-length' => ['12345']]);
        $httpClient = static::createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $service = new MediaUploadService(
            $this->mediaRepository,
            $this->fileFetcher,
            $this->fileSaver,
            $this->eventDispatcher,
            $httpClient,
            $this->mediaThumbnailRepository,
            $this->mediaThumbnailSizeRepository,
            $fileUrlValidator,
            false,
        );

        $params = new MediaUploadParameters();
        $params->mimeType = 'image/jpeg';

        $service->linkURL('http://10.0.0.1/image.jpg', $this->context, $params);

        static::assertCount(1, $this->mediaRepository->creates);
    }

    public function testAddExternalThumbnailsRejectsPrivateIpUrl(): void
    {
        $validator = static::createStub(FileUrlValidatorInterface::class);
        $validator->method('isValid')->willReturn(false);

        $service = new MediaUploadService(
            $this->mediaRepository,
            $this->fileFetcher,
            $this->fileSaver,
            $this->eventDispatcher,
            $this->httpClient,
            $this->mediaThumbnailRepository,
            $this->mediaThumbnailSizeRepository,
            $validator,
        );

        $thumbnails = new ExternalThumbnailCollection([
            new ExternalThumbnailData('http://10.0.0.1/thumb.jpg', 100, 100),
        ]);

        $this->expectExceptionObject(MediaException::illegalUrl('http://10.0.0.1/thumb.jpg'));

        $service->addExternalThumbnailsToMedia(Uuid::randomHex(), $thumbnails, $this->context);
    }

    public function testCreateExternalThumbnailsSkipsCreateWhenCollectionIsEmpty(): void
    {
        $mediaId = Uuid::randomHex();
        $collection = new ExternalThumbnailCollection([]);

        $this->mediaUploadService->addExternalThumbnailsToMedia($mediaId, $collection, $this->context);

        static::assertCount(0, $this->mediaThumbnailRepository->creates);
    }

    public function testIsExternalUrl(): void
    {
        static::assertTrue(MediaUploadService::isExternalUrl('http://localhost:8000/image.jpg'));
        static::assertTrue(MediaUploadService::isExternalUrl('https://localhost:8000/image.jpg'));
        static::assertFalse(MediaUploadService::isExternalUrl('file:///image.jpg'));
        static::assertFalse(MediaUploadService::isExternalUrl('/image.jpg'));
    }

    private function buildService(
        ?FileFetcher $fileFetcher = null,
        ?FileSaver $fileSaver = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?HttpClientInterface $httpClient = null,
        ?FileUrlValidatorInterface $fileUrlValidator = null,
    ): MediaUploadService {
        $service = new MediaUploadService(
            $this->mediaRepository,
            $fileFetcher ?? $this->fileFetcher,
            $fileSaver ?? $this->fileSaver,
            $eventDispatcher ?? $this->eventDispatcher,
            $httpClient ?? $this->httpClient,
            $this->mediaThumbnailRepository,
            $this->mediaThumbnailSizeRepository,
            $fileUrlValidator ?? $this->fileUrlValidator,
        );

        $this->mediaUploadService = $service;

        return $service;
    }
}

// Create fixtures directory structure
if (!is_dir(__DIR__ . '/fixtures')) {
    mkdir(__DIR__ . '/fixtures', 0777, true);
}
