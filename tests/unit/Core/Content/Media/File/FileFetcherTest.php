<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileService;
use Shopware\Core\Content\Media\File\FileUrlValidatorInterface;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Covers the guards that reject a fetch before or while the destination is written. The destination is an
 * in-memory stream, so the successful fetches, which detect the mime type of the written file, live in
 * \Shopware\Tests\Integration\Core\Content\Media\File\FileFetcherTest.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FileFetcher::class)]
class FileFetcherTest extends TestCase
{
    private const IMAGE_URL_WITH_EXTENSION = __DIR__ . '/_fixtures/image1x1.png';
    private const EMPTY_FILE_URL = __DIR__ . '/_fixtures/empty';
    private const IMAGE_FILE_SIZE = 95;
    private const IMAGE_EXTENSION = 'png';
    private const DESTINATION = 'php://memory';

    #[DataProvider('fetchRequestExceptionsDataProvider')]
    public function testFetchRequestDataWillThrowException(
        int $contentLength,
        string $extension,
        \Exception $expectedException
    ): void {
        $fileFetcher = $this->createFileFetcher();

        $content = fopen(self::IMAGE_URL_WITH_EXTENSION, 'r');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', $extension);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', (string) $contentLength);

        $this->expectExceptionObject($expectedException);
        $fileFetcher->fetchRequestData($request, self::DESTINATION);
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

    public function testFetchRequestDataThrowsOnEmptyFile(): void
    {
        $fileFetcher = $this->createFileFetcher();

        $content = fopen(self::EMPTY_FILE_URL, 'r');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', self::IMAGE_EXTENSION);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', '0');

        $this->expectExceptionObject(MediaException::emptyFile());
        $fileFetcher->fetchRequestData($request, self::DESTINATION);
    }

    public function testFetchFromURLThrowsOnEmptyFile(): void
    {
        $fileFetcher = $this->createFileFetcher(httpClient: $this->mockResponseFor(self::EMPTY_FILE_URL));

        $this->expectExceptionObject(MediaException::emptyFile());

        $fileFetcher->fetchFromURL('https://example.com/empty', self::DESTINATION, self::IMAGE_EXTENSION);
    }

    public function testFetchFromURLRefusesWhenTheConnectedAddressIsPrivate(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse('body', ['primary_ip' => '169.254.169.254'])
        );

        // resolves public, so only the client guard can reject the connected address
        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']);

        $fileFetcher = $this->createFileFetcher(resolver: $resolver, httpClient: $httpClient);

        $this->expectExceptionObject(MediaException::cannotOpenSourceStreamToRead('https://media.example.com/image.png'));

        $fileFetcher->fetchFromURL('https://media.example.com/image.png', self::DESTINATION, self::IMAGE_EXTENSION);
    }

    public function testFetchFromURLThrowsIllegalUrlWhenResolutionIsPrivate(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            self::fail('The connection must not be attempted for a private resolution');
        });

        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['10.0.0.1']);

        $fileFetcher = $this->createFileFetcher(resolver: $resolver, httpClient: $httpClient);

        $this->expectExceptionObject(MediaException::illegalUrl('https://media.example.com/image.png'));

        $fileFetcher->fetchFromURL('https://media.example.com/image.png', self::DESTINATION, self::IMAGE_EXTENSION);
    }

    #[DataProvider('fetchFileFromUrlExceptionsDataProvider')]
    public function testFetchFileFromURLWillThrowException(
        bool $enableUploadFeature,
        bool $isUrl,
        bool $isValid,
        string $urlParameter,
        \Exception $expectedException
    ): void {
        $fileServiceMock = static::createStub(FileService::class);
        $fileServiceMock->method('isUrl')->willReturn($isUrl);

        $fileValidatorMock = static::createStub(FileUrlValidatorInterface::class);
        $fileValidatorMock->method('isValid')->willReturn($isValid);

        $httpClient = new MockHttpClient(static function (): MockResponse {
            self::fail('No request must be issued when validation rejects the URL');
        });

        $fileFetcher = $this->createFileFetcher(
            validator: $fileValidatorMock,
            fileService: $fileServiceMock,
            httpClient: $httpClient,
            enableUploadFeature: $enableUploadFeature,
        );

        $request = new Request();
        $request->query->set('extension', self::IMAGE_EXTENSION);
        $request->request->set('url', $urlParameter);

        $this->expectExceptionObject($expectedException);
        $fileFetcher->fetchFileFromURL($request, self::DESTINATION);
    }

    public static function fetchFileFromUrlExceptionsDataProvider(): \Generator
    {
        yield 'disableUrlUploadFeature exception' => [
            'enableUploadFeature' => false,
            'isUrl' => true,
            'isValid' => true,
            'urlParameter' => 'https://example.com/image.png',
            'expectedException' => MediaException::disableUrlUploadFeature(),
        ];
        yield 'invalidUrl exception' => [
            'enableUploadFeature' => true,
            'isUrl' => false,
            'isValid' => true,
            'urlParameter' => 'https://example.com/image.png',
            'expectedException' => MediaException::invalidUrl('https://example.com/image.png'),
        ];
        yield 'illegalUrl exception' => [
            'enableUploadFeature' => true,
            'isUrl' => true,
            'isValid' => false,
            'urlParameter' => 'https://example.com/image.png',
            'expectedException' => MediaException::illegalUrl('https://example.com/image.png'),
        ];
        yield 'missingUrlParameter exception' => [
            'enableUploadFeature' => true,
            'isUrl' => true,
            'isValid' => false,
            'urlParameter' => '',
            'expectedException' => MediaException::missingUrlParameter(),
        ];
    }

    public function testFetchRequestDataThrowsWhenDestinationStreamCannotBeOpened(): void
    {
        $fileFetcher = $this->createFileFetcher();

        $content = fopen(self::IMAGE_URL_WITH_EXTENSION, 'r');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', self::IMAGE_EXTENSION);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', (string) self::IMAGE_FILE_SIZE);

        $this->expectExceptionObject(MediaException::cannotOpenSourceStreamToWrite(''));

        $fileFetcher->fetchRequestData($request, '');
    }

    public function testFetchFileFromURLThrowsWhenSourceExceedsLimit(): void
    {
        $fileFetcher = $this->createFileFetcher(
            httpClient: $this->mockResponseFor(self::IMAGE_URL_WITH_EXTENSION),
            maxFileSize: 1,
        );

        $this->expectExceptionObject(MediaException::fileSizeLimitExceeded());

        $fileFetcher->fetchFromURL('https://example.com/image.png', self::DESTINATION, self::IMAGE_EXTENSION);
    }

    private function createFileFetcher(
        ?FileUrlValidatorInterface $validator = null,
        ?FileService $fileService = null,
        ?TrustedUrlResolver $resolver = null,
        ?HttpClientInterface $httpClient = null,
        bool $enableUploadFeature = true,
        int $maxFileSize = 0,
    ): FileFetcher {
        if ($validator === null) {
            $validator = static::createStub(FileUrlValidatorInterface::class);
            $validator->method('isValid')->willReturn(true);
        }

        if ($fileService === null) {
            $fileService = static::createStub(FileService::class);
            $fileService->method('isUrl')->willReturn(true);
        }

        return new FileFetcher(
            $validator,
            $fileService,
            $resolver ?? new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']),
            $httpClient ?? new MockHttpClient(),
            $enableUploadFeature,
            true,
            $maxFileSize,
        );
    }

    private function mockResponseFor(string $fixture): MockHttpClient
    {
        return new MockHttpClient(new MockResponse((string) file_get_contents($fixture)));
    }
}
