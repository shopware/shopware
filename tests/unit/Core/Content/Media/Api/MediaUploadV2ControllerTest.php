<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\MediaUploadV2Controller;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailCollection;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailData;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailsParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaUploadV2Controller::class)]
class MediaUploadV2ControllerTest extends TestCase
{
    private MediaUploadService&MockObject $mediaUploadService;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private MediaUploadV2Controller $controller;

    protected function setUp(): void
    {
        $this->mediaUploadService = $this->createMock(MediaUploadService::class);
        $this->mediaRepository = new StaticEntityRepository([]);
        $this->controller = new MediaUploadV2Controller(
            $this->mediaUploadService,
            $this->mediaRepository
        );
    }

    public function testUpload(): void
    {
        $mediaId = Uuid::randomHex();
        $request = new Request();
        $context = Context::createDefaultContext();

        $this->mediaUploadService
            ->expects($this->once())
            ->method('uploadFromRequest')
            ->with($request, $context, static::isInstanceOf(MediaUploadParameters::class))
            ->willReturn($mediaId);

        $response = $this->controller->upload($request, new MediaUploadParameters(), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testUploadUrl(): void
    {
        $mediaId = Uuid::randomHex();
        $url = 'https://example.com/image.jpg';
        $request = new Request([], ['url' => $url]);
        $context = Context::createDefaultContext();

        $this->mediaUploadService
            ->expects($this->once())
            ->method('uploadFromURL')
            ->with($url, $context, static::isInstanceOf(MediaUploadParameters::class))
            ->willReturn($mediaId);

        $response = $this->controller->uploadUrl($request, new MediaUploadParameters(), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testUploadUrlWithMissingUrl(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(MediaException::invalidUrl(''));

        $this->controller->uploadUrl($request, new MediaUploadParameters(), $context);
    }

    public function testUploadUrlWithNonStringUrl(): void
    {
        $request = new Request([], ['url' => 123]);
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(MediaException::invalidUrl('123'));

        $this->controller->uploadUrl($request, new MediaUploadParameters(), $context);
    }

    public function testExternalLink(): void
    {
        $mediaId = Uuid::randomHex();
        $url = 'https://example.com/image.jpg';
        $request = new Request([], ['url' => $url]);
        $context = Context::createDefaultContext();

        $this->mediaUploadService
            ->expects($this->once())
            ->method('linkURL')
            ->with($url, $context, static::isInstanceOf(MediaUploadParameters::class))
            ->willReturn($mediaId);

        $response = $this->controller->externalLink($request, new MediaUploadParameters(), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testExternalLinkWithMissingUrl(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);

        $this->controller->externalLink($request, new MediaUploadParameters(), $context);
    }

    public function testExternalLinkWithNonStringUrl(): void
    {
        $request = new Request([], ['url' => 123]);
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(MediaException::invalidUrl('123'));
        $this->controller->externalLink($request, new MediaUploadParameters(), $context);
    }

    public function testAddExternalThumbnails(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPath('http://localhost:8000/image.jpg');

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $params = new ExternalThumbnailsParameters(new ExternalThumbnailCollection([
            new ExternalThumbnailData('http://localhost:8000/thumb-200.jpg', 200, 200),
            new ExternalThumbnailData('http://localhost:8000/thumb-400.jpg', 400, 400),
        ]));

        $this->mediaUploadService
            ->expects($this->once())
            ->method('addExternalThumbnailsToMedia')
            ->with($mediaId, static::callback(static fn ($arg) => $arg instanceof ExternalThumbnailCollection && $arg->count() === 2), $context);

        $response = $this->controller->addExternalThumbnails($mediaId, $params, $context);

        static::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame($mediaId, $content['mediaId']);
        static::assertSame(2, $content['thumbnailsCreated']);
    }

    public function testAddExternalThumbnailsWithNonExternalMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPath('media/image.jpg');

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->expectExceptionObject(MediaException::externalMediaRequired($mediaId));

        $this->controller->addExternalThumbnails($mediaId, new ExternalThumbnailsParameters(), $context);
    }

    public function testAddExternalThumbnailsWithNonExistentMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->mediaRepository->addSearch(new MediaCollection([]));

        $this->expectExceptionObject(MediaException::mediaNotFound($mediaId));

        $this->controller->addExternalThumbnails($mediaId, new ExternalThumbnailsParameters(), $context);
    }

    public function testDeleteExternalThumbnails(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPath('http://localhost:8000/image.jpg');

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->mediaUploadService
            ->expects($this->once())
            ->method('deleteAllExternalThumbnails')
            ->with($mediaId, $context);

        $response = $this->controller->deleteExternalThumbnails($mediaId, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame($mediaId, $content['mediaId']);
    }

    public function testDeleteExternalThumbnailsWithNonExternalMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPath('media/image.jpg');

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->expectExceptionObject(MediaException::externalMediaRequired($mediaId));

        $this->controller->deleteExternalThumbnails($mediaId, $context);
    }

    public function testDeleteExternalThumbnailsWithNonExistentMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->mediaRepository->addSearch(new MediaCollection([]));

        $this->expectExceptionObject(MediaException::mediaNotFound($mediaId));

        $this->controller->deleteExternalThumbnails($mediaId, $context);
    }

    public function testAddExternalThumbnailsThrowsWhenMediaHasNoPath(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->expectExceptionObject(MediaException::emptyMediaPath($mediaId));

        $this->controller->addExternalThumbnails($mediaId, new ExternalThumbnailsParameters(), $context);
    }

    public function testDeleteExternalThumbnailsThrowsWhenMediaHasNoPath(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->expectExceptionObject(MediaException::emptyMediaPath($mediaId));

        $this->controller->deleteExternalThumbnails($mediaId, $context);
    }
}
