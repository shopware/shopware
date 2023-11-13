<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\File\DownloadResponseGenerator
 */
#[Package('buyers-experience')]
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
            $publicFilesystem,
            $this->privateFilesystem,
            $this->mediaService,
            'php',
            $this->createMock(AbstractMediaUrlGenerator::class)
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
            $this->createMock(FilesystemOperator::class),
            $this->createMock(FilesystemOperator::class),
            $this->mediaService,
            'php',
            $this->createMock(AbstractMediaUrlGenerator::class)
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

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('The file "foobar." does not exist');
        $this->downloadResponseGenerator->getResponse($media, $this->salesChannelContext);
    }

    /**
     * @dataProvider filesystemProvider
     */
    public function testGetResponse(bool $private, string $privateType, string $publicType, Response $expectedResponse, ?string $strategy = null): void
    {
        $privateFilesystem = $privateType === 'local' ? $this->getLocaleFilesystemOperator() : $this->getExternalFilesystemOperator();
        $publicFilesystem = $publicType === 'local' ? $this->getLocaleFilesystemOperator() : $this->getExternalFilesystemOperator();

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('foobar');
        $media->setFileExtension('txt');
        $media->setPrivate($private);
        $media->setPath('foobar.txt');

        $generator = $this->createMock(AbstractMediaUrlGenerator::class);
        $generator->method('generate')->willReturn([$media->getId() => 'foobar.txt']);

        $this->downloadResponseGenerator = new DownloadResponseGenerator(
            $privateFilesystem,
            $publicFilesystem,
            $this->mediaService,
            $strategy ?? 'php',
            $generator
        );

        $streamInterface = $this->createMock(StreamInterface::class);
        $streamInterface->method('detach')->willReturn(fopen('php://temp', 'rb'));
        $this->mediaService->method('loadFileStream')->willReturn($streamInterface);

        $response = $this->downloadResponseGenerator->getResponse($media, $this->salesChannelContext);

        $response->headers->set('date', null);
        $expectedResponse->headers->set('date', null);

        static::assertEquals($expectedResponse, $response);
    }

    public static function filesystemProvider(): \Generator
    {
        yield 'private / aws' => [true, 'external', 'external', new RedirectResponse('foobar.txt')];
        yield 'public / aws' => [false, 'external', 'external', new RedirectResponse('foobar.txt')];
        yield 'private / google' => [true, 'external', 'external', new RedirectResponse('foobar.txt')];
        yield 'public / google' => [false, 'external', 'external', new RedirectResponse('foobar.txt')];
        yield 'private / local / php' => [true, 'local', 'local', self::getExpectedStreamResponse()];
        yield 'private / local / x-sendfile' => [
            true,
            'local',
            'local',
            self::getExpectedStreamResponse('X-Sendfile'),
            DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGRY,
        ];
        yield 'private / local / x-accel' => [
            true,
            'local',
            'local',
            self::getExpectedStreamResponse('X-Accel-Redirect'),
            DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGRY,
        ];
        yield 'public / local' => [false, 'local', 'local', new RedirectResponse('foobar.txt')];
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

    private static function getExpectedStreamResponse(?string $strategy = null): Response
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
