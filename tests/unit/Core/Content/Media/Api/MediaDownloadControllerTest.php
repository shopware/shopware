<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\Api\MediaDownloadController;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\AssertResponseHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaDownloadController::class)]
class MediaDownloadControllerTest extends TestCase
{
    private MediaService&MockObject $mediaService;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private MediaDownloadController $controller;

    protected function setUp(): void
    {
        $this->mediaService = $this->createMock(MediaService::class);
        $this->mediaRepository = new StaticEntityRepository([]);

        $this->controller = new MediaDownloadController(
            $this->mediaRepository,
            $this->mediaService
        );
    }

    public function testDownloadMediaFileStreamsPrivateMedia(): void
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

        $stream = static::createStub(StreamInterface::class);
        $stream->method('detach')->willReturn(fopen('php://temp', 'r'));

        $this->mediaService
            ->expects($this->once())
            ->method('loadFileStream')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn($stream);

        $response = $this->controller->downloadMediaFile($mediaId, $context);

        AssertResponseHelper::assertResponseEquals(self::createExpectedStreamResponse(), $response);
    }

    public function testDownloadMediaFileStreamsPublicMedia(): void
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

        $stream = static::createStub(StreamInterface::class);
        $stream->method('detach')->willReturn(fopen('php://temp', 'r'));

        $this->mediaService
            ->expects($this->once())
            ->method('loadFileStream')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn($stream);

        $response = $this->controller->downloadMediaFile($mediaId, $context);

        AssertResponseHelper::assertResponseEquals(self::createExpectedStreamResponse(), $response);
    }

    public function testDownloadMediaFileThrowsIfMediaDoesNotExist(): void
    {
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->addSearch(new MediaCollection());

        $this->mediaService
            ->expects($this->never())
            ->method('loadFileStream');

        $this->expectExceptionObject(MediaException::mediaNotFound($mediaId));

        $this->controller->downloadMediaFile($mediaId, Context::createDefaultContext());
    }

    private static function createExpectedStreamResponse(): StreamedResponse
    {
        return new StreamedResponse(static function (): void {
        }, Response::HTTP_OK, [
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'foobar.txt', 'foobar.txt'),
            'Content-Length' => 123,
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
