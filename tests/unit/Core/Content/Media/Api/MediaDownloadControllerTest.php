<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\MediaDownloadController;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaDownloadController::class)]
class MediaDownloadControllerTest extends TestCase
{
    private DownloadResponseGenerator&MockObject $downloadResponseGenerator;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private MediaDownloadController $controller;

    protected function setUp(): void
    {
        $this->downloadResponseGenerator = $this->createMock(DownloadResponseGenerator::class);
        $this->mediaRepository = new StaticEntityRepository([]);

        $this->controller = new MediaDownloadController(
            $this->mediaRepository,
            $this->downloadResponseGenerator
        );
    }

    public function testDownloadMediaFileUsesDownloadResponseGenerator(): void
    {
        $mediaId = Uuid::randomHex();
        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPrivate(true);
        $media->setFileName('foobar');
        $media->setFileExtension('txt');
        $media->setFileSize(123);
        $context = Context::createDefaultContext();

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $expectedResponse = new StreamedResponse(static function (): void {});
        $this->downloadResponseGenerator
            ->expects($this->once())
            ->method('getResponseByContext')
            ->with($media, $context)
            ->willReturn($expectedResponse);

        $response = $this->controller->downloadMediaFile($mediaId, $context);

        static::assertSame($expectedResponse, $response);
    }

    public function testPrepareMediaDownloadReturnsRedirectTargetUrl(): void
    {
        $mediaId = Uuid::randomHex();
        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPrivate(false);
        $media->setFileName('foobar');
        $media->setFileExtension('txt');
        $media->setFileSize(123);
        $context = Context::createDefaultContext();

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->downloadResponseGenerator
            ->expects($this->once())
            ->method('getResponseByContext')
            ->with($media, $context)
            ->willReturn(new RedirectResponse('https://cdn.example.test/download'));

        $response = $this->controller->prepareMediaDownload($mediaId, $context);

        static::assertSame(
            json_encode(['type' => 'external', 'url' => 'https://cdn.example.test/download'], \JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    public function testPrepareMediaDownloadSignalsBlobFallbackWhenGeneratorNeedsLocalResponse(): void
    {
        $mediaId = Uuid::randomHex();
        $media = new MediaEntity();
        $media->setId($mediaId);
        $context = Context::createDefaultContext();

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $expectedResponse = new StreamedResponse(static function (): void {});

        $this->downloadResponseGenerator
            ->expects($this->once())
            ->method('getResponseByContext')
            ->with($media, $context)
            ->willReturn($expectedResponse);

        $response = $this->controller->prepareMediaDownload($mediaId, $context);

        static::assertSame(
            json_encode(['type' => 'blob'], \JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    public function testDownloadMediaFileThrowsIfMediaDoesNotExist(): void
    {
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->addSearch(new MediaCollection());

        $this->downloadResponseGenerator
            ->expects($this->never())
            ->method('getResponseByContext');

        $this->expectExceptionObject(MediaException::mediaNotFound($mediaId));

        $this->controller->downloadMediaFile($mediaId, Context::createDefaultContext());
    }

    public function testPrepareMediaDownloadThrowsIfMediaDoesNotExist(): void
    {
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->addSearch(new MediaCollection());

        $this->downloadResponseGenerator
            ->expects($this->never())
            ->method('getResponseByContext');

        $this->expectExceptionObject(MediaException::mediaNotFound($mediaId));

        $this->controller->prepareMediaDownload($mediaId, Context::createDefaultContext());
    }
}
