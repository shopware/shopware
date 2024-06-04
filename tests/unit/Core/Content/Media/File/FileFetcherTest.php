<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileService;
use Shopware\Core\Content\Media\File\FileUrlValidatorInterface;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(FileFetcher::class)]
class FileFetcherTest extends TestCase
{
    private const IMAGE_URL_WITHOUT_EXTENSION = __DIR__ . '/_fixtures/image1x1';
    private const IMAGE_URL_WITH_EXTENSION = __DIR__ . '/_fixtures/image1x1.png';
    private const BINARY_FILE_URL_WITHOUT_EXTENSION = __DIR__ . '/_fixtures/binary';
    private const IMAGE_FILE_SIZE = 95;
    private const IMAGE_EXTENSION = 'png';
    private const IMAGE_MIME_TYPE = 'image/png';
    private const MIME_TYPE_FOR_UNDETECTED_FORMATS = 'application/octet-stream';
    private const EXTENSION_FOR_UNDETECTED_FORMATS = 'bin';
    private const TEMP_DIR = __DIR__ . '/_temp';
    private const TEMP_FILE = __DIR__ . '/_temp/expected';

    protected function setUp(): void
    {
        $this->createTemporyDirectory();
    }

    protected function tearDown(): void
    {
        $this->deleteTemporyData();
    }

    public function testFetchRequestData(): void
    {
        $fileValidatorMock = $this->createMock(FileUrlValidatorInterface::class);
        $fileService = new FileService();
        $fileFetcher = new FileFetcher($fileValidatorMock, $fileService);

        $content = fopen(self::IMAGE_URL_WITHOUT_EXTENSION, 'r');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', self::IMAGE_EXTENSION);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', (string) self::IMAGE_FILE_SIZE);

        $media = $fileFetcher->fetchRequestData($request, self::TEMP_FILE);

        static::assertSame(self::IMAGE_MIME_TYPE, $media->getMimeType());
        static::assertSame(self::IMAGE_EXTENSION, $media->getFileExtension());
    }

    #[DataProvider('fetchRequestExceptionsDataProvider')]
    public function testFetchRequestDataWillThrowException(
        int $contentLength,
        string $extension,
        \Exception $expectedException
    ): void {
        $fileValidatorMock = $this->createMock(FileUrlValidatorInterface::class);
        $fileService = new FileService();
        $fileFetcher = new FileFetcher($fileValidatorMock, $fileService);

        $content = fopen(self::IMAGE_URL_WITH_EXTENSION, 'r');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', $extension);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', (string) $contentLength);

        $this->expectExceptionObject($expectedException);
        $fileFetcher->fetchRequestData($request, self::TEMP_FILE);
    }

    public static function fetchRequestExceptionsDataProvider(): \Generator
    {
        yield 'invalidContentLength exception' => [
            'contentLength' => 42,
            'extension' => self::IMAGE_EXTENSION,
            'expectedException' => MediaException::invalidContentLength(),
        ];
        yield 'resource without an extension' => [
            'contentLength' => self::IMAGE_FILE_SIZE,
            'extension' => '',
            'expectedException' => MediaException::missingFileExtension(),
        ];
    }

    #[DataProvider('fetchFileFromUrlDataProvider')]
    public function testFetchFileFromURL(
        string $file,
        string $providedExtension,
        string $expectedMimeType,
        string $expectedExtension
    ): void {
        $fileValidatorMock = $this->createMock(FileUrlValidatorInterface::class);
        $fileValidatorMock->method('isValid')->willReturn(true);

        $fileServiceMock = $this->createMock(FileService::class);
        $fileServiceMock->method('isUrl')->willReturn(true);

        $fileFetcher = new FileFetcher($fileValidatorMock, $fileServiceMock);

        $request = new Request();
        $request->query->set('extension', $providedExtension);
        $request->request->set('url', $file);

        $media = $fileFetcher->fetchFileFromURL($request, self::TEMP_FILE);

        static::assertSame($expectedMimeType, $media->getMimeType());
        static::assertSame($expectedExtension, $media->getFileExtension());
    }

    public static function fetchFileFromUrlDataProvider(): \Generator
    {
        yield 'image resource without an extension' => [
            'file' => self::IMAGE_URL_WITHOUT_EXTENSION,
            'providedExtension' => self::IMAGE_EXTENSION,
            'expectedMimeType' => self::IMAGE_MIME_TYPE,
            'expectedExtension' => self::IMAGE_EXTENSION,
        ];
        yield 'image resource with extension' => [
            'file' => self::IMAGE_URL_WITH_EXTENSION,
            'providedExtension' => '',
            'expectedMimeType' => self::IMAGE_MIME_TYPE,
            'expectedExtension' => self::IMAGE_EXTENSION,
        ];
        yield 'binary file without extension' => [
            'file' => self::BINARY_FILE_URL_WITHOUT_EXTENSION,
            'providedExtension' => '',
            'expectedMimeType' => self::MIME_TYPE_FOR_UNDETECTED_FORMATS,
            'expectedExtension' => self::EXTENSION_FOR_UNDETECTED_FORMATS,
        ];
    }

    #[DataProvider('fetchFileFromUrlExceptionsDataProvider')]
    public function testFetchFileFromURLWillThrowException(
        bool $enableUploadFeature,
        bool $isUrl,
        bool $isValid,
        string $urlParameter,
        \Exception $expectedException
    ): void {
        $fileServiceMock = $this->createMock(FileService::class);
        $fileServiceMock->method('isUrl')->willReturn($isUrl);

        $fileValidatorMock = $this->createMock(FileUrlValidatorInterface::class);
        $fileValidatorMock->method('isValid')->willReturn($isValid);

        $fileFetcher = new FileFetcher($fileValidatorMock, $fileServiceMock, $enableUploadFeature);

        $request = new Request();
        $request->query->set('extension', self::IMAGE_EXTENSION);
        $request->request->set('url', $urlParameter);

        $this->expectExceptionObject($expectedException);
        $fileFetcher->fetchFileFromURL($request, self::TEMP_FILE);
    }

    public static function fetchFileFromUrlExceptionsDataProvider(): \Generator
    {
        yield 'disableUrlUploadFeature exception' => [
            'enableUploadFeature' => false,
            'isUrl' => true,
            'isValid' => true,
            'urlParameter' => self::IMAGE_URL_WITH_EXTENSION,
            'expectedException' => MediaException::disableUrlUploadFeature(),
        ];
        yield 'invalidUrl exception' => [
            'enableUploadFeature' => true,
            'isUrl' => false,
            'isValid' => true,
            'urlParameter' => self::IMAGE_URL_WITH_EXTENSION,
            'expectedException' => MediaException::invalidUrl(self::IMAGE_URL_WITH_EXTENSION),
        ];
        yield 'illegalUrl exception' => [
            'enableUploadFeature' => true,
            'isUrl' => true,
            'isValid' => false,
            'urlParameter' => self::IMAGE_URL_WITH_EXTENSION,
            'expectedException' => MediaException::illegalUrl(self::IMAGE_URL_WITH_EXTENSION),
        ];
        yield 'missingUrlParameter exception' => [
            'enableUploadFeature' => true,
            'isUrl' => true,
            'isValid' => false,
            'urlParameter' => '',
            'expectedException' => MediaException::missingUrlParameter(),
        ];
    }

    private function createTemporyDirectory(): void
    {
        if (!is_dir(self::TEMP_DIR)) {
            mkdir(self::TEMP_DIR);
            static::assertDirectoryExists(self::TEMP_DIR);
        }
    }

    private function deleteTemporyData(): void
    {
        if (file_exists(self::TEMP_FILE)) {
            unlink(self::TEMP_FILE);
        }

        if (is_dir(self::TEMP_DIR)) {
            static::assertTrue(rmdir(self::TEMP_DIR));
        }
    }
}
