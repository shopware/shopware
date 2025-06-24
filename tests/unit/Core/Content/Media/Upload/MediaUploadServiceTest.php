<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
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

    private FileFetcher&MockObject $fileFetcher;

    private FileSaver&MockObject $fileSaver;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private HttpClientInterface&MockObject $httpClient;

    private MediaUploadService $mediaUploadService;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaRepository = new StaticEntityRepository([]);
        $this->fileFetcher = $this->createMock(FileFetcher::class);
        $this->fileSaver = $this->createMock(FileSaver::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->mediaUploadService = new MediaUploadService(
            $this->mediaRepository,
            $this->fileFetcher,
            $this->fileSaver,
            $this->eventDispatcher,
            $this->httpClient
        );

        $this->context = Context::createDefaultContext();
    }

    public function testUploadFromLocalPath(): void
    {
        $filePath = __DIR__ . '/fixtures/test-image.jpg';
        $params = new MediaUploadParameters();

        (new Filesystem())->dumpFile($filePath, 'test content');

        $this->fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia')
            ->with(
                static::isInstanceOf(MediaFile::class),
                static::isString(),
                static::isString(),
                $this->context
            );

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MediaUploadedEvent::class));

        $result = $this->mediaUploadService->uploadFromLocalPath($filePath, $this->context, $params);

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

        $this->fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MediaUploadedEvent::class));

        $result = $this->mediaUploadService->uploadFromRequest($request, $this->context, $params);

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
        $mediaId = Uuid::randomHex();

        $mediaFile = new MediaFile(
            'test.jpg',
            'image/jpeg',
            'jpg',
            1024,
            'test-hash'
        );

        $tmpDir = sys_get_temp_dir();
        static::assertNotEmpty($tmpDir);

        $this->fileFetcher
            ->expects($this->once())
            ->method('fetchFromURL')
            ->with(
                $url,
                static::stringStartsWith($tmpDir)
            )
            ->willReturn($mediaFile);

        $this->fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MediaUploadedEvent::class));

        $result = $this->mediaUploadService->uploadFromURL($url, $this->context, $params);

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
        $mediaId = Uuid::randomHex();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([
            'content-length' => ['1024'],
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('HEAD', $url)
            ->willReturn($response);

        $result = $this->mediaUploadService->linkURL($url, $this->context, $params);

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

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('HEAD', $url)
            ->willReturn($response);

        $this->expectException(MediaException::class);

        $this->mediaUploadService->linkURL($url, $this->context, $params);
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

        $this->httpClient->expects($this->never())->method('request');

        $result = $this->mediaUploadService->linkURL($url, $this->context, $params);

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

        $adminSource = $this->createMock(AdminApiSource::class);
        $adminSource->method('getUserId')->willReturn($userId);

        $adminContext = Context::createDefaultContext($adminSource);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([
            'content-length' => ['1024'],
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->mediaUploadService->linkURL($url, $adminContext, $params);

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

        $this->fileSaver->expects($this->never())->method('persistFileToMedia');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $result = $this->mediaUploadService->uploadFromLocalPath($filePath, $this->context, $params);

        static::assertSame($existingMediaId, $result);
        static::assertCount(0, $this->mediaRepository->creates);

        (new Filesystem())->remove($filePath);
    }

    public function testUploadWithErrorHandling(): void
    {
        $filePath = __DIR__ . '/fixtures/test-image.jpg';
        $params = new MediaUploadParameters();

        (new Filesystem())->dumpFile($filePath, 'test content');

        $this->fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia')
            ->willThrowException(new \Exception('Upload failed'));

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Upload failed');

        try {
            $this->mediaUploadService->uploadFromLocalPath($filePath, $this->context, $params);
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

        $this->fileSaver
            ->expects($this->once())
            ->method('persistFileToMedia')
            ->with(
                static::isInstanceOf(MediaFile::class),
                'custom-name',
                $customId,
                $this->context
            );

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $result = $this->mediaUploadService->uploadFromLocalPath($filePath, $this->context, $params);

        static::assertSame($customId, $result);
        static::assertCount(1, $this->mediaRepository->creates);

        $createdMedia = $this->mediaRepository->creates[0][0];
        static::assertSame($customId, $createdMedia['id']);
        static::assertTrue($createdMedia['private']);
        static::assertSame($mediaFolderId, $createdMedia['mediaFolderId']);

        (new Filesystem())->remove($filePath);
    }
}

// Create fixtures directory structure
if (!is_dir(__DIR__ . '/fixtures')) {
    mkdir(__DIR__ . '/fixtures', 0777, true);
}
