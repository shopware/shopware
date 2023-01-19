<?php declare(strict_types=1);

namespace unit\php\Core\Content\Media\File;

use Aws\CommandInterface;
use Aws\S3\S3ClientInterface;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Superbalist\Flysystem\GoogleStorage\GoogleStorageAdapter;
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
    /**
     * @var MockObject|MediaService
     */
    private $mediaService;

    /**
     * @var MockObject|Filesystem
     */
    private $privateFilesystem;

    /**
     * @var MockObject|Filesystem
     */
    private $publicFilesystem;

    /**
     * @var MockObject|UrlGeneratorInterface
     */
    private $urlGenerator;

    private DownloadResponseGenerator $downloadResponseGenerator;

    /**
     * @var MockObject|SalesChannelContext
     */
    private $salesChannelContext;

    public function setUp(): void
    {
        $this->mediaService = $this->createMock(MediaService::class);
        $this->privateFilesystem = $this->createMock(Filesystem::class);
        $this->publicFilesystem = $this->createMock(Filesystem::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('getAbsoluteMediaUrl')->willReturn('foobar.txt');
        $this->urlGenerator->method('getRelativeMediaUrl')->willReturn('foobar.txt');

        $this->downloadResponseGenerator = new DownloadResponseGenerator(
            $this->publicFilesystem,
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
            $this->createMock(FilesystemInterface::class),
            $this->createMock(FilesystemInterface::class),
            $this->urlGenerator,
            $this->mediaService,
            'php'
        );

        static::expectException(\RuntimeException::class);
        $downloadResponseGenerator->getResponse($media, $this->salesChannelContext);
    }

    public function testThrowsExceptionWithoutDetachableResource(): void
    {
        static::expectException(FileNotFoundException::class);

        $this->privateFilesystem->method('getAdapter')->willReturn($this->createMock(AbstractAdapter::class));

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('foobar');
        $media->setPrivate(true);

        $this->downloadResponseGenerator->getResponse($media, $this->salesChannelContext);
    }

    /**
     * @param MockObject|AdapterInterface|null $adapter
     *
     * @dataProvider filesystemProvider
     */
    public function testGetResponse(bool $private, $adapter, Response $expectedResponse, ?string $strategy = null): void
    {
        $this->privateFilesystem->method('getAdapter')->willReturn($adapter);
        $this->publicFilesystem->method('getAdapter')->willReturn($adapter);

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('foobar');
        $media->setFileExtension('txt');
        $media->setPrivate($private);

        $streamInterface = $this->createMock(StreamInterface::class);
        $streamInterface->method('detach')->willReturn(fopen('php://temp', 'rb'));
        $this->mediaService->method('loadFileStream')->willReturn($streamInterface);

        if ($strategy) {
            $property = ReflectionHelper::getProperty(DownloadResponseGenerator::class, 'localPrivateDownloadStrategory');
            $property->setValue($this->downloadResponseGenerator, $strategy);
        }

        $response = $this->downloadResponseGenerator->getResponse($media, $this->salesChannelContext);

        $response->headers->set('date', null);
        $expectedResponse->headers->set('date', null);

        static::assertEquals($expectedResponse, $response);
    }

    public function filesystemProvider(): \Generator
    {
        $defaultAdapter = $this->createMock(AbstractAdapter::class);

        yield 'private / aws' => [true, $this->getAwsS3AdapterMock(), new RedirectResponse('foobar.txt')];
        yield 'public / aws' => [false, $this->getAwsS3AdapterMock(), new RedirectResponse('foobar.txt')];
        yield 'private / google' => [true, $this->getGoogleStorageAdapterMock(), new RedirectResponse('foobar.txt')];
        yield 'public / google' => [false, $this->getGoogleStorageAdapterMock(), new RedirectResponse('foobar.txt')];
        yield 'private / local / php' => [true, $defaultAdapter, $this->getExpectedStreamResponse()];
        yield 'private / local / x-sendfile' => [
            true,
            $defaultAdapter,
            $this->getExpectedStreamResponse('X-Sendfile'),
            DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGRY,
        ];
        yield 'private / local / x-accel' => [
            true,
            $defaultAdapter,
            $this->getExpectedStreamResponse('X-Accel-Redirect'),
            DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGRY,
        ];
        yield 'public / local' => [false, $defaultAdapter, new RedirectResponse('foobar.txt')];
    }

    /**
     * @return AwsS3Adapter|MockObject
     */
    private function getAwsS3AdapterMock()
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $requestInterface = $this->createMock(RequestInterface::class);
        $requestInterface->method('getUri')->willReturn('foobar.txt');
        $client->method('createPresignedRequest')->willReturn($requestInterface);
        $adapter = $this->createMock(AwsS3Adapter::class);
        $adapter->method('getClient')->willReturn($client);

        return $adapter;
    }

    /**
     * @return GoogleStorageAdapter|MockObject
     */
    private function getGoogleStorageAdapterMock()
    {
        $adapter = $this->createMock(GoogleStorageAdapter::class);
        $adapter->method('getTemporaryUrl')->willReturn('foobar.txt');

        return $adapter;
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
