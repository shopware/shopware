<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\Exception\AppDownloadException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[CoversClass(AppDownloader::class)]
class AppDownloaderTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;

    private Filesystem&MockObject $filesystem;

    private AppDownloader $appDownloader;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);

        $this->appDownloader = new AppDownloader($this->httpClient, $this->filesystem);
    }

    public function testDownloadThrowsExceptionOnNon200Response(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(AppDownloadException::class);
        $this->expectExceptionMessage('App could not be downloaded from: "http://example.com/file.zip".');

        $this->appDownloader->download('http://example.com/file.zip', '/path/to/file.zip');
    }

    public function testStreamingDownloadCreatesDirectory(): void
    {
        $this->filesystem->expects(static::once())
            ->method('mkdir')
            ->with(static::equalTo('/path/to'));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $generator = function () use ($response): \Generator {
            yield $response => new DataChunk(0, 'chunk1content');
            yield $response => new DataChunk(1, 'chunk2content');
        };

        $stream = new ResponseStream($generator());

        $this->httpClient->method('request')->willReturn($response);
        $this->httpClient->method('stream')->willReturn($stream);

        $matcher = static::exactly(2);

        $this->filesystem
            ->expects($matcher)
            ->method('appendToFile')
            ->willReturnOnConsecutiveCalls()
            ->willReturnCallback(function (string $file, $content) use ($matcher): void {
                $this->assertEquals('/path/to/file.zip', $file);
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals('chunk1content', $content),
                    2 => $this->assertEquals('chunk2content', $content),
                    default => null,
                };
            });

        $this->appDownloader->download('http://example.com/file.zip', '/path/to/file.zip');
    }

    public function testDownloadFromFilesystem(): void
    {
        $fs = new \League\Flysystem\Filesystem(new InMemoryFilesystemAdapter());
        $fs->write('/some/file.zip', 'content');

        $this->filesystem->expects(static::once())
            ->method('dumpFile')
            ->willReturnCallback(function (string $path, $contentResource): void {
                static::assertEquals('/path/to/file.zip', $path);
                static::assertEquals('content', stream_get_contents($contentResource));
            });

        $this->appDownloader->downloadFromFilesystem($fs, '/some/file.zip', '/path/to/file.zip');
    }

    public function testDownloadFromFilesystemWrapsException(): void
    {
        $this->expectException(AppDownloadException::class);
        $this->expectExceptionMessage('App could not be downloaded from: "/some/file.zip".');

        $fs = new \League\Flysystem\Filesystem(new InMemoryFilesystemAdapter());

        $this->filesystem->expects(static::never())->method('dumpFile');

        $this->appDownloader->downloadFromFilesystem($fs, '/some/file.zip', '/path/to/file.zip');
    }
}
