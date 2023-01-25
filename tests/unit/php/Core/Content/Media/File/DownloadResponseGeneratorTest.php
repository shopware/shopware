<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\File\DownloadResponseGenerator
 */
class DownloadResponseGeneratorTest extends TestCase
{
    private MockObject&MediaService $mediaService;

    private Filesystem&MockObject $privateFilesystem;

    private MockObject&UrlGeneratorInterface $urlGenerator;

    private DownloadResponseGenerator $downloadResponseGenerator;

    private MockObject&SalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        $this->mediaService = $this->createMock(MediaService::class);
        $this->privateFilesystem = $this->createMock(Filesystem::class);
        $publicFilesystem = $this->createMock(Filesystem::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('getAbsoluteMediaUrl')->willReturn('foobar.txt');
        $this->urlGenerator->method('getRelativeMediaUrl')->willReturn('foobar.txt');

        $this->downloadResponseGenerator = new DownloadResponseGenerator(
            $publicFilesystem,
            $this->privateFilesystem,
            $this->urlGenerator,
            $this->mediaService,
            'php'
        );

        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
    }

    public function testThrowsExceptionWithoutFilesystemAdapter(): void
    {
        $media = new MediaEntity();
        $media->setFileName('foobar');

        $downloadResponseGenerator = new DownloadResponseGenerator(
            $this->createMock(FilesystemOperator::class),
            $this->createMock(FilesystemOperator::class),
            $this->urlGenerator,
            $this->mediaService,
            'php'
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

        $this->expectException(FileNotFoundException::class);
        $this->downloadResponseGenerator->getResponse($media, $this->salesChannelContext);
    }

    /**
     * @dataProvider filesystemProvider
     */
    public function testGetResponse(bool $private, FilesystemOperator $privateFilesystem, FilesystemOperator $publicFilesystem, Response $expectedResponse, ?string $strategy = null): void
    {
        $this->downloadResponseGenerator = new DownloadResponseGenerator(
            $privateFilesystem,
            $publicFilesystem,
            $this->urlGenerator,
            $this->mediaService,
            $strategy ?? 'php'
        );

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('foobar');
        $media->setFileExtension('txt');
        $media->setPrivate($private);

        $streamInterface = $this->createMock(StreamInterface::class);
        $streamInterface->method('detach')->willReturn(fopen('php://temp', 'rb'));
        $this->mediaService->method('loadFileStream')->willReturn($streamInterface);

        $response = $this->downloadResponseGenerator->getResponse($media, $this->salesChannelContext);

        $response->headers->set('date', null);
        $expectedResponse->headers->set('date', null);

        static::assertEquals($expectedResponse, $response);
    }

    public function filesystemProvider(): \Generator
    {
        yield 'private / aws' => [true, $this->getExternalFilesystemOperator(), $this->getExternalFilesystemOperator(), new RedirectResponse('foobar.txt')];
        yield 'public / aws' => [false, $this->getExternalFilesystemOperator(), $this->getExternalFilesystemOperator(), new RedirectResponse('foobar.txt')];
        yield 'private / google' => [true, $this->getExternalFilesystemOperator(), $this->getExternalFilesystemOperator(), new RedirectResponse('foobar.txt')];
        yield 'public / google' => [false, $this->getExternalFilesystemOperator(), $this->getExternalFilesystemOperator(), new RedirectResponse('foobar.txt')];
        yield 'private / local / php' => [true, $this->getLocaleFilesystemOperator(), $this->getLocaleFilesystemOperator(), $this->getExpectedStreamResponse()];
        yield 'private / local / x-sendfile' => [
            true,
            $this->getLocaleFilesystemOperator(),
            $this->getLocaleFilesystemOperator(),
            $this->getExpectedStreamResponse('X-Sendfile'),
            DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGRY,
        ];
        yield 'private / local / x-accel' => [
            true,
            $this->getLocaleFilesystemOperator(),
            $this->getLocaleFilesystemOperator(),
            $this->getExpectedStreamResponse('X-Accel-Redirect'),
            DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGRY,
        ];
        yield 'public / local' => [false, $this->getLocaleFilesystemOperator(), $this->getLocaleFilesystemOperator(), new RedirectResponse('foobar.txt')];
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

    private function getExpectedStreamResponse(?string $strategy = null): Response
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
            $response->headers->set($strategy, 'foobar.txt');

            return $response;
        }

        return new StreamedResponse(function (): void {
        }, Response::HTTP_OK, $headers);
    }
}
