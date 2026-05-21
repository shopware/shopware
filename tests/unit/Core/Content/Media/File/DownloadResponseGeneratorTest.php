<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\AssertResponseHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DownloadResponseGenerator::class)]
class DownloadResponseGeneratorTest extends TestCase
{
    private MockObject&MediaService $mediaService;

    private Filesystem&MockObject $privateFilesystem;

    private DownloadResponseGenerator $downloadResponseGenerator;

    private MockObject&SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->mediaService = $this->createMock(MediaService::class);
        $this->privateFilesystem = $this->createMock(Filesystem::class);
        $publicFilesystem = $this->createMock(Filesystem::class);

        $this->downloadResponseGenerator = new DownloadResponseGenerator(
            $this->createMock(LoggerInterface::class),
            $publicFilesystem,
            $this->privateFilesystem,
            $this->mediaService,
            'php',
            $this->createMock(AbstractMediaUrlGenerator::class),
            new NativeClock(),
            ''
        );

        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
    }

    public function testThrowsExceptionWithoutFilesystemAdapter(): void
    {
        $media = new MediaEntity();
        $media->setFileName('foobar');
        $media->setPath('foobar.txt');

        $downloadResponseGenerator = new DownloadResponseGenerator(
            $this->createMock(LoggerInterface::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(FilesystemOperator::class),
            $this->mediaService,
            'php',
            $this->createMock(AbstractMediaUrlGenerator::class),
            new NativeClock(),
            ''
        );

        $this->expectException(\RuntimeException::class);
        $downloadResponseGenerator->getResponse($media, $this->salesChannelContext);
    }

    public function testThrowsExceptionWithoutDetachableResource(): void
    {
        $this->privateFilesystem->method('temporaryUrl')->willThrowException(new UnableToGenerateTemporaryUrl('foo', 'baa'));

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('foobar');
        $media->setPrivate(true);
        $media->setPath('foobar.txt');

        $this->expectExceptionObject(MediaException::fileNotFound('foobar.'));
        $this->downloadResponseGenerator->getResponse($media, $this->salesChannelContext);
    }

    #[DataProvider('filesystemProvider')]
    public function testGetResponse(bool $private, string $type, Response $expectedResponse, ?string $strategy = null, string $privateLocalPathPrefix = ''): void
    {
        $privateFilesystem = $type === 'local' ? $this->getLocaleFilesystemOperator() : $this->getExternalFilesystemOperator();
        $publicFilesystem = $type === 'local' ? $this->getLocaleFilesystemOperator() : $this->getExternalFilesystemOperator();

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('foobar');
        $media->setFileExtension('txt');
        $media->setPrivate($private);
        $media->setPath('foobar.txt');

        $generator = $this->createMock(AbstractMediaUrlGenerator::class);
        $generator->method('generate')->willReturn([$media->getId() => 'foobar.txt']);

        $this->downloadResponseGenerator = new DownloadResponseGenerator(
            $this->createMock(LoggerInterface::class),
            $privateFilesystem,
            $publicFilesystem,
            $this->mediaService,
            $strategy ?? 'php',
            $generator,
            new NativeClock(),
            $privateLocalPathPrefix
        );

        $streamInterface = $this->createMock(StreamInterface::class);
        $streamInterface->method('detach')->willReturn(fopen('php://temp', 'r'));
        $this->mediaService->method('loadFileStream')->willReturn($streamInterface);

        $response = $this->downloadResponseGenerator->getResponse($media, $this->salesChannelContext);

        AssertResponseHelper::assertResponseEquals($expectedResponse, $response);
    }

    public static function filesystemProvider(): \Generator
    {
        yield 'private / aws' => [true, 'external', new RedirectResponse('foobar.txt')];
        yield 'public / aws' => [false, 'external', new RedirectResponse('foobar.txt')];
        yield 'private / local / php' => [true, 'local', self::getExpectedStreamResponse()];
        yield 'private / local / x-sendfile' => [
            true,
            'local',
            self::getExpectedStreamResponse(DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY),
            DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY,
        ];
        yield 'private / local / x-accel' => [
            true,
            'local',
            self::getExpectedStreamResponse(DownloadResponseGenerator::X_ACCEL_REDIRECT),
            DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGY,
        ];
        yield 'private / local / x-accel with prefix' => [
            true,
            'local',
            self::getExpectedStreamResponse(DownloadResponseGenerator::X_ACCEL_REDIRECT, '/protected'),
            DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGY,
            '/protected',
        ];
        yield 'public / local' => [false, 'local', new RedirectResponse('foobar.txt')];
    }

    public function testGetResponseUsingAzureBlobStorageWithUnsupportedAuth(): void
    {
        $fileSystem = $this->createMock(Filesystem::class);
        $expectedException = new \Exception('UnableToGenerateSasException');
        $fileSystem->method('temporaryUrl')->willThrowException($expectedException);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                static::equalTo('UnableToGenerateSasException'),
                static::equalTo(['exception' => $expectedException]),
            );

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('foobar');
        $media->setFileExtension('txt');
        $media->setPrivate(true);
        $media->setPath('foobar.txt');

        $generator = $this->createMock(AbstractMediaUrlGenerator::class);
        $generator->method('generate')->willReturn([$media->getId() => 'foobar.txt']);

        $downloadResponseGenerator = new DownloadResponseGenerator(
            $logger,
            $fileSystem,
            $fileSystem,
            $this->mediaService,
            'php',
            $generator,
            new NativeClock(),
            ''
        );

        $streamInterface = $this->createMock(StreamInterface::class);
        $streamInterface->method('detach')->willReturn(fopen('php://temp', 'r'));
        $this->mediaService->method('loadFileStream')->willReturn($streamInterface);

        $response = $downloadResponseGenerator->getResponse($media, $this->salesChannelContext);

        AssertResponseHelper::assertResponseEquals(self::getExpectedStreamResponse(), $response);
    }

    /**
     * @return Filesystem&MockObject
     */
    private function getLocaleFilesystemOperator(): Filesystem
    {
        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->method('temporaryUrl')->willThrowException(new UnableToGenerateTemporaryUrl('reason', 'path'));

        return $fileSystem;
    }

    private function getExternalFilesystemOperator(): Filesystem&MockObject
    {
        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->method('temporaryUrl')->willReturn('foobar.txt');

        return $fileSystem;
    }

    private static function getExpectedStreamResponse(?string $strategy = null, string $privateLocalPathPrefix = ''): Response
    {
        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                'foobar.txt',
                'foobar.txt'
            ),
            'Content-Length' => 0,
            'Content-Type' => 'application/octet-stream',
        ];

        if ($strategy) {
            $response = new Response(null, 200, $headers);

            $locationPath = 'foobar.txt';
            if ($strategy === DownloadResponseGenerator::X_ACCEL_REDIRECT && $privateLocalPathPrefix !== '') {
                $locationPath = $privateLocalPathPrefix . '/foobar.txt';
            }

            $response->headers->set($strategy, $locationPath);

            return $response;
        }

        return new StreamedResponse(static function (): void {
        }, Response::HTTP_OK, $headers);
    }
}
