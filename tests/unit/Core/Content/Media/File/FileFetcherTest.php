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
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FileFetcher::class)]
class FileFetcherTest extends TestCase
{
    private const IMAGE_URL_WITHOUT_EXTENSION = __DIR__ . '/_fixtures/image1x1';
    private const IMAGE_URL_WITH_EXTENSION = __DIR__ . '/_fixtures/image1x1.png';
    private const BINARY_FILE_URL_WITHOUT_EXTENSION = __DIR__ . '/_fixtures/binary';
    private const EMPTY_FILE_URL = __DIR__ . '/_fixtures/empty';
    private const IMAGE_FILE_SIZE = 95;
    private const IMAGE_EXTENSION = 'png';
    private const IMAGE_MIME_TYPE = 'image/png';
    private const MIME_TYPE_FOR_UNDETECTED_FORMATS = 'application/octet-stream';
    private const EXTENSION_FOR_UNDETECTED_FORMATS = 'bin';
    private const TEMP_DIR = __DIR__ . '/_temp';
    private const TEMP_FILE = __DIR__ . '/_temp/expected';

    private ?Process $server = null;

    protected function setUp(): void
    {
        $this->createTemporyDirectory();
    }

    protected function tearDown(): void
    {
        $this->server?->stop(0);
        $this->deleteTemporyData();
    }

    public function testFetchRequestData(): void
    {
        $fileFetcher = $this->createFileFetcher();

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
        $fileFetcher = $this->createFileFetcher();

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
        $fileFetcher->fetchRequestData($request, self::TEMP_FILE);
    }

    public function testFetchFromURLThrowsOnEmptyFile(): void
    {
        $fileFetcher = $this->createFileFetcher(httpClient: $this->mockResponseFor(self::EMPTY_FILE_URL));

        $this->expectExceptionObject(MediaException::emptyFile());

        try {
            $fileFetcher->fetchFromURL('https://example.com/empty', self::TEMP_FILE, self::IMAGE_EXTENSION);
        } finally {
            if (\is_file(self::TEMP_FILE)) {
                unlink(self::TEMP_FILE);
            }
        }
    }

    #[DataProvider('fetchFileFromUrlDataProvider')]
    public function testFetchFileFromURL(
        string $file,
        string $providedExtension,
        string $expectedMimeType,
        string $expectedExtension
    ): void {
        $fileFetcher = $this->createFileFetcher(httpClient: $this->mockResponseFor($file));

        $request = new Request();
        $request->query->set('extension', $providedExtension);
        $request->request->set('url', 'https://example.com/download');

        $media = $fileFetcher->fetchFileFromURL($request, self::TEMP_FILE);

        static::assertSame($expectedMimeType, $media->getMimeType());
        static::assertSame($expectedExtension, $media->getFileExtension());
    }

    public function testFetchFileFromURLAppliesConfiguredTimeout(): void
    {
        $capturedOptions = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;

            return new MockResponse((string) file_get_contents(self::IMAGE_URL_WITH_EXTENSION));
        });

        $fileFetcher = $this->createFileFetcher(httpClient: $httpClient, urlUploadTimeout: 1.5);

        $fileFetcher->fetchFromURL('https://example.com/image.jpg', self::TEMP_FILE, self::IMAGE_EXTENSION);

        static::assertSame(1.5, $capturedOptions['max_duration']);
    }

    public function testFetchFileFromURLDoesNotApplyTimeoutByDefault(): void
    {
        $capturedOptions = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;

            return new MockResponse((string) file_get_contents(self::IMAGE_URL_WITH_EXTENSION));
        });

        $fileFetcher = $this->createFileFetcher(httpClient: $httpClient);

        $fileFetcher->fetchFromURL('https://example.com/image.jpg', self::TEMP_FILE, self::IMAGE_EXTENSION);

        static::assertTrue(!isset($capturedOptions['max_duration']) || $capturedOptions['max_duration'] <= 0);
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

    public function testFetchFromURLPinsValidatedIpOntoConnection(): void
    {
        $capturedOptions = [];
        $body = (string) file_get_contents(self::IMAGE_URL_WITH_EXTENSION);

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions, $body): MockResponse {
            $capturedOptions = $options;

            return new MockResponse($body);
        });

        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']);

        $fileFetcher = $this->createFileFetcher(resolver: $resolver, httpClient: $httpClient);

        $fileFetcher->fetchFromURL('https://media.example.com/image.png', self::TEMP_FILE, self::IMAGE_EXTENSION);

        static::assertSame(0, $capturedOptions['max_redirects'] ?? null);
        static::assertArrayHasKey('resolve', $capturedOptions);
        static::assertContains('93.184.216.34', $capturedOptions['resolve']);
    }

    public function testFetchFromURLPinsEvenWhenValidationIsDisabled(): void
    {
        $capturedOptions = [];
        $body = (string) file_get_contents(self::IMAGE_URL_WITH_EXTENSION);

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions, $body): MockResponse {
            $capturedOptions = $options;

            return new MockResponse($body);
        });

        $resolver = new TrustedUrlResolver(
            static fn (string $host): array => ['127.0.0.1'],
            rejectPrivateRanges: false,
        );

        $fileFetcher = $this->createFileFetcher(
            resolver: $resolver,
            httpClient: $httpClient,
            enableValidation: false,
        );

        $fileFetcher->fetchFromURL('http://localhost:8000/image.png', self::TEMP_FILE, self::IMAGE_EXTENSION);

        static::assertArrayHasKey('resolve', $capturedOptions);
        static::assertContains('127.0.0.1', $capturedOptions['resolve']);
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

        try {
            $fileFetcher->fetchFromURL('https://media.example.com/image.png', self::TEMP_FILE, self::IMAGE_EXTENSION);
        } finally {
            if (\is_file(self::TEMP_FILE)) {
                unlink(self::TEMP_FILE);
            }
        }
    }

    public function testFetchFromURLThrowsIllegalUrlWhenResolutionIsPrivate(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            self::fail('The connection must not be attempted for a private resolution');
        });

        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['10.0.0.1']);

        $fileFetcher = $this->createFileFetcher(resolver: $resolver, httpClient: $httpClient);

        $this->expectExceptionObject(MediaException::illegalUrl('https://media.example.com/image.png'));

        $fileFetcher->fetchFromURL('https://media.example.com/image.png', self::TEMP_FILE, self::IMAGE_EXTENSION);
    }

    public function testFetchFromURLReachesAllowedPrivateAddress(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse((string) file_get_contents(self::IMAGE_URL_WITH_EXTENSION), ['primary_ip' => '10.0.0.5'])
        );

        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['10.0.0.5'], allowedPrivateIps: ['10.0.0.5']);

        $this->createFileFetcher(resolver: $resolver, httpClient: $httpClient)
            ->fetchFromURL('https://media.example.com/image.png', self::TEMP_FILE, self::IMAGE_EXTENSION);

        static::assertFileEquals(self::IMAGE_URL_WITH_EXTENSION, self::TEMP_FILE);
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
        $fileFetcher->fetchFileFromURL($request, self::TEMP_FILE);
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

    public function testFetchBlobCreatesTemporaryFileAndCleanUpDeletesIt(): void
    {
        $fileFetcher = $this->createFileFetcher();

        $media = $fileFetcher->fetchBlob('myBlob', self::IMAGE_EXTENSION, self::IMAGE_MIME_TYPE);

        try {
            static::assertFileExists($media->getFileName());
            static::assertSame('myBlob', (string) file_get_contents($media->getFileName()));
            static::assertSame(self::IMAGE_EXTENSION, $media->getFileExtension());
            static::assertSame(self::IMAGE_MIME_TYPE, $media->getMimeType());
        } finally {
            if (\is_file($media->getFileName())) {
                $fileFetcher->cleanUpTempFile($media);
            }
        }

        static::assertFileDoesNotExist($media->getFileName());
    }

    public function testFetchFileFromURLWithLimitInRange(): void
    {
        $fileFetcher = $this->createFileFetcher(
            httpClient: $this->mockResponseFor(self::IMAGE_URL_WITH_EXTENSION),
            maxFileSize: self::IMAGE_FILE_SIZE + 1,
        );

        $media = $fileFetcher->fetchFromURL('https://example.com/image.png', self::TEMP_FILE, self::IMAGE_EXTENSION);

        static::assertSame(self::IMAGE_FILE_SIZE, $media->getFileSize());
        static::assertSame(self::IMAGE_MIME_TYPE, $media->getMimeType());
        static::assertSame(self::IMAGE_EXTENSION, $media->getFileExtension());
    }

    public function testFetchFileFromURLThrowsWhenSourceExceedsLimit(): void
    {
        $fileFetcher = $this->createFileFetcher(
            httpClient: $this->mockResponseFor(self::IMAGE_URL_WITH_EXTENSION),
            maxFileSize: 1,
        );

        $this->expectExceptionObject(MediaException::fileSizeLimitExceeded());

        try {
            $fileFetcher->fetchFromURL('https://example.com/image.png', self::TEMP_FILE, self::IMAGE_EXTENSION);
        } finally {
            if (\is_file(self::TEMP_FILE)) {
                unlink(self::TEMP_FILE);
            }
        }
    }

    public function testUrlUploadLimitDoesNotAffectRequestUpload(): void
    {
        $fileFetcher = $this->createFileFetcher(maxFileSize: 10);

        $content = fopen(self::IMAGE_URL_WITHOUT_EXTENSION, 'r');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', self::IMAGE_EXTENSION);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', (string) self::IMAGE_FILE_SIZE);

        $media = $fileFetcher->fetchRequestData($request, self::TEMP_FILE);

        static::assertSame(self::IMAGE_FILE_SIZE, $media->getFileSize());
        static::assertFileExists(self::TEMP_FILE);
    }

    public function testFetchFromURLDownloadsFromFtpServer(): void
    {
        $port = $this->startFtpServer(pasvAddress: '127.0.0.1', epsv: true);

        $media = $this->createFileFetcher(resolver: $this->createLoopbackResolver())
            ->fetchFromURL('ftp://ftp.example.com:' . $port . '/image1x1.png', self::TEMP_FILE);

        static::assertSame(self::IMAGE_MIME_TYPE, $media->getMimeType());
        static::assertFileEquals(self::IMAGE_URL_WITH_EXTENSION, self::TEMP_FILE);
    }

    public function testFetchFromURLIgnoresPrivateAddressInPasvReply(): void
    {
        $port = $this->startFtpServer(pasvAddress: '10.255.255.1', epsv: false);

        $this->createFileFetcher(resolver: $this->createLoopbackResolver(), urlUploadTimeout: 5.0)
            ->fetchFromURL('ftp://ftp.example.com:' . $port . '/image1x1.png', self::TEMP_FILE);

        static::assertFileEquals(self::IMAGE_URL_WITH_EXTENSION, self::TEMP_FILE);
    }

    public function testFetchFromURLRejectsFtpHostResolvingToPrivateAddress(): void
    {
        $port = $this->startFtpServer(pasvAddress: '127.0.0.1', epsv: true);
        $url = 'ftp://ftp.example.com:' . $port . '/image1x1.png';

        $this->expectExceptionObject(MediaException::illegalUrl($url));

        $this->createFileFetcher(resolver: $this->createLoopbackResolver(allowedPrivateIps: []))->fetchFromURL($url, self::TEMP_FILE);
    }

    public function testFetchFromURLRejectsFtpFileExceedingTheSizeLimit(): void
    {
        $port = $this->startFtpServer(pasvAddress: '127.0.0.1', epsv: true);

        $this->expectExceptionObject(MediaException::fileSizeLimitExceeded());

        $this->createFileFetcher(resolver: $this->createLoopbackResolver(), maxFileSize: 50)
            ->fetchFromURL('ftp://ftp.example.com:' . $port . '/image1x1.png', self::TEMP_FILE);
    }

    public function testFetchFromURLAbortsStalledFtpTransfer(): void
    {
        $port = $this->startFtpServer(pasvAddress: '127.0.0.1', epsv: true, stall: true);
        $url = 'ftp://ftp.example.com:' . $port . '/image1x1.png';
        $socketTimeout = (string) \ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '1');

        $start = microtime(true);

        try {
            $this->createFileFetcher(resolver: $this->createLoopbackResolver())->fetchFromURL($url, self::TEMP_FILE);
            static::fail('The stalled transfer should be aborted.');
        } catch (MediaException $exception) {
            static::assertEquals(MediaException::cannotOpenSourceStreamToRead($url), $exception);
        } finally {
            ini_set('default_socket_timeout', $socketTimeout);
        }

        static::assertLessThan(10, microtime(true) - $start);
    }

    public function testFetchFromURLIgnoresProxyFromEnvironmentForFtp(): void
    {
        $port = $this->startFtpServer(pasvAddress: '127.0.0.1', epsv: true);
        putenv('all_proxy=http://127.0.0.1:9');

        try {
            $this->createFileFetcher(resolver: $this->createLoopbackResolver())
                ->fetchFromURL('ftp://ftp.example.com:' . $port . '/image1x1.png', self::TEMP_FILE);
        } finally {
            putenv('all_proxy');
        }

        static::assertFileEquals(self::IMAGE_URL_WITH_EXTENSION, self::TEMP_FILE);
    }

    private function startFtpServer(string $pasvAddress, bool $epsv, bool $stall = false): int
    {
        $this->server = new Process([
            (string) (new PhpExecutableFinder())->find(),
            __DIR__ . '/_fixtures/ftp-server.php',
            self::IMAGE_URL_WITH_EXTENSION,
            $pasvAddress,
            $epsv ? '1' : '0',
            $stall ? '1' : '0',
        ]);
        $this->server->start();
        $this->server->waitUntil(static fn (string $type, string $output): bool => str_contains($output, \PHP_EOL));

        return (int) trim($this->server->getOutput());
    }

    /**
     * @param list<string> $allowedPrivateIps
     */
    private function createLoopbackResolver(array $allowedPrivateIps = ['127.0.0.1']): TrustedUrlResolver
    {
        return new TrustedUrlResolver(static fn (string $host): array => ['127.0.0.1'], allowedPrivateIps: $allowedPrivateIps, allowedSchemes: ['ftp']);
    }

    private function createFileFetcher(
        ?FileUrlValidatorInterface $validator = null,
        ?FileService $fileService = null,
        ?TrustedUrlResolver $resolver = null,
        ?HttpClientInterface $httpClient = null,
        bool $enableUploadFeature = true,
        bool $enableValidation = true,
        int $maxFileSize = 0,
        float $urlUploadTimeout = 0.0,
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
            $enableValidation,
            $maxFileSize,
            $urlUploadTimeout,
        );
    }

    private function mockResponseFor(string $fixture): MockHttpClient
    {
        return new MockHttpClient(new MockResponse((string) file_get_contents($fixture)));
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
        if (\is_file(self::TEMP_FILE)) {
            unlink(self::TEMP_FILE);
        }

        if (\is_dir(self::TEMP_DIR)) {
            static::assertTrue(rmdir(self::TEMP_DIR));
        }
    }
}
