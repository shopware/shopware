<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\File\PrivateFileDownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PrivateFileDownloadResponseGenerator::class)]
class PrivateFileDownloadResponseGeneratorTest extends TestCase
{
    public function testCreatesStreamedResponseFromTheProvidedStream(): void
    {
        $providerCalled = false;
        $generator = new PrivateFileDownloadResponseGenerator();

        $response = $generator->createResponse(
            streamProvider: function () use (&$providerCalled): StreamInterface {
                $providerCalled = true;

                return (new Psr17Factory())->createStream('file contents');
            },
            headers: ['Content-Type' => 'text/plain'],
            downloadStrategy: 'php',
            path: 'private/file.txt',
        );

        static::assertInstanceOf(StreamedResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertTrue($providerCalled);
    }

    public function testCreatesXAccelResponseWithoutOpeningTheFile(): void
    {
        $providerCalled = false;
        $generator = new PrivateFileDownloadResponseGenerator();

        $response = $generator->createResponse(
            streamProvider: function () use (&$providerCalled): StreamInterface {
                $providerCalled = true;

                return (new Psr17Factory())->createStream('file contents');
            },
            headers: ['Content-Type' => 'text/plain'],
            downloadStrategy: DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGY,
            path: 'private/file.txt',
            pathPrefix: '/protected',
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('/protected/private/file.txt', $response->headers->get(DownloadResponseGenerator::X_ACCEL_REDIRECT));
        static::assertFalse($providerCalled);
    }

    public function testCreatesXSendfileResponseFromTheProvidedStream(): void
    {
        $providerCalled = false;
        $generator = new PrivateFileDownloadResponseGenerator();
        $resource = fopen(__DIR__ . '/_fixtures/empty', 'r');
        static::assertIsResource($resource);

        $response = $generator->createResponse(
            streamProvider: function () use (&$providerCalled, $resource): StreamInterface {
                $providerCalled = true;

                return (new Psr17Factory())->createStreamFromResource($resource);
            },
            headers: ['Content-Type' => 'text/plain'],
            downloadStrategy: DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY,
            path: 'private/file.txt',
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(__DIR__ . '/_fixtures/empty', $response->headers->get(DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY));
        static::assertTrue($providerCalled);
    }

    #[DataProvider('nonLocalStreamProvider')]
    public function testXSendfileFallsBackToStreamingForNonLocalStreams(string $uri): void
    {
        $resource = fopen($uri, 'w+b');
        static::assertIsResource($resource);
        fwrite($resource, 'file contents');
        rewind($resource);

        $response = (new PrivateFileDownloadResponseGenerator())->createResponse(
            streamProvider: static fn (): StreamInterface => (new Psr17Factory())->createStreamFromResource($resource),
            headers: ['Content-Type' => 'text/plain', 'Content-Length' => '13'],
            downloadStrategy: DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY,
            path: 'private/file.txt',
        );

        static::assertInstanceOf(StreamedResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertFalse($response->headers->has(DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY));
        static::assertSame('text/plain', $response->headers->get('Content-Type'));
        static::assertSame('13', $response->headers->get('Content-Length'));
        $this->expectOutputString('file contents');
        $response->sendContent();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonLocalStreamProvider(): iterable
    {
        yield 'temporary stream used by S3' => ['php://temp'];
        yield 'memory stream' => ['php://memory'];
    }

    public function testThrowsFileNotFoundWhenTheProvidedStreamCannotBeDetached(): void
    {
        $generator = new PrivateFileDownloadResponseGenerator();
        $stream = static::createStub(StreamInterface::class);
        $stream->method('detach')->willReturn(null);

        $this->expectExceptionObject(MediaException::fileNotFound('private/file.txt'));

        $generator->createResponse(
            streamProvider: static fn (): StreamInterface => $stream,
            headers: ['Content-Type' => 'text/plain'],
            downloadStrategy: 'php',
            path: 'private/file.txt',
        );
    }
}
