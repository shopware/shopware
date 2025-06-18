<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\MediaUploadV2Controller;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    private MediaUploadV2Controller $controller;

    protected function setUp(): void
    {
        $this->mediaUploadService = $this->createMock(MediaUploadService::class);
        $this->controller = new MediaUploadV2Controller($this->mediaUploadService);
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

        $response = $this->controller->upload($request, $context);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testUploadWithParameters(): void
    {
        $mediaId = Uuid::randomHex();
        $request = new Request([], [
            'id' => 'custom-id',
            'fileName' => 'test.jpg',
            'private' => 'true',
            'mediaFolderId' => 'folder-id',
            'mimeType' => 'image/jpeg',
            'deduplicate' => 'false',
        ]);
        $context = Context::createDefaultContext();

        $this->mediaUploadService
            ->expects($this->once())
            ->method('uploadFromRequest')
            ->with(
                $request,
                $context,
                static::callback(function (MediaUploadParameters $params) {
                    static::assertSame('custom-id', $params->id);
                    static::assertSame('test.jpg', $params->fileName);
                    static::assertTrue($params->private);
                    static::assertSame('folder-id', $params->mediaFolderId);
                    static::assertSame('image/jpeg', $params->mimeType);
                    static::assertFalse($params->deduplicate);

                    return true;
                })
            )
            ->willReturn($mediaId);

        $response = $this->controller->upload($request, $context);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
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

        $response = $this->controller->uploadUrl($request, $context);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testUploadUrlWithInvalidUrl(): void
    {
        $request = new Request([], ['url' => null]);
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);

        $this->controller->uploadUrl($request, $context);
    }

    public function testUploadUrlWithNonStringUrl(): void
    {
        $request = new Request([], ['url' => ['invalid' => 'array']]);
        $context = Context::createDefaultContext();

        $this->expectException(\TypeError::class);

        $this->controller->uploadUrl($request, $context);
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

        $response = $this->controller->externalLink($request, $context);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testExternalLinkWithInvalidUrl(): void
    {
        $request = new Request([], ['url' => null]);
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);

        $this->controller->externalLink($request, $context);
    }

    public function testExternalLinkWithNonStringUrl(): void
    {
        $request = new Request([], ['url' => 123]);
        $context = Context::createDefaultContext();

        $this->expectException(\TypeError::class);

        $this->controller->externalLink($request, $context);
    }

    public function testBuildMediaUploadParamsFromRequestWithAllParameters(): void
    {
        $request = new Request([], [
            'id' => 'test-id',
            'fileName' => 'test.jpg',
            'private' => 'true',
            'mediaFolderId' => 'folder-id',
            'mimeType' => 'image/jpeg',
            'deduplicate' => 'false',
        ]);
        $context = Context::createDefaultContext();

        $mediaId = Uuid::randomHex();
        $this->mediaUploadService
            ->expects($this->once())
            ->method('uploadFromRequest')
            ->with(
                $request,
                $context,
                static::callback(function (MediaUploadParameters $params) {
                    static::assertSame('test-id', $params->id);
                    static::assertSame('test.jpg', $params->fileName);
                    static::assertTrue($params->private);
                    static::assertSame('folder-id', $params->mediaFolderId);
                    static::assertSame('image/jpeg', $params->mimeType);
                    static::assertFalse($params->deduplicate);

                    return true;
                })
            )
            ->willReturn($mediaId);

        $this->controller->upload($request, $context);
    }

    public function testBuildMediaUploadParamsFromRequestWithBooleanPrivate(): void
    {
        $request = new Request([], [
            'private' => true,
            'deduplicate' => false,
        ]);
        $context = Context::createDefaultContext();

        $mediaId = Uuid::randomHex();
        $this->mediaUploadService
            ->expects($this->once())
            ->method('uploadFromRequest')
            ->with(
                $request,
                $context,
                static::callback(function (MediaUploadParameters $params) {
                    static::assertTrue($params->private);
                    static::assertFalse($params->deduplicate);

                    return true;
                })
            )
            ->willReturn($mediaId);

        $this->controller->upload($request, $context);
    }

    public function testBuildMediaUploadParamsFromRequestWithStringBooleans(): void
    {
        $request = new Request([], [
            'private' => '1',
            'deduplicate' => '0',
        ]);
        $context = Context::createDefaultContext();

        $mediaId = Uuid::randomHex();
        $this->mediaUploadService
            ->expects($this->once())
            ->method('uploadFromRequest')
            ->with(
                $request,
                $context,
                static::callback(function (MediaUploadParameters $params) {
                    static::assertTrue($params->private);
                    static::assertFalse($params->deduplicate);

                    return true;
                })
            )
            ->willReturn($mediaId);

        $this->controller->upload($request, $context);
    }

    public function testBuildMediaUploadParamsFromRequestWithInvalidTypes(): void
    {
        $request = new Request([], [
            'id' => ['invalid'],
            'fileName' => 123,
            'private' => 'invalid',
            'mediaFolderId' => false,
            'mimeType' => [],
            'deduplicate' => 'invalid',
        ]);
        $context = Context::createDefaultContext();

        $mediaId = Uuid::randomHex();
        $this->mediaUploadService
            ->expects($this->once())
            ->method('uploadFromRequest')
            ->with(
                $request,
                $context,
                static::callback(function (MediaUploadParameters $params) {
                    static::assertNull($params->id);
                    static::assertNull($params->fileName);
                    static::assertFalse($params->private);
                    static::assertNull($params->mediaFolderId);
                    static::assertNull($params->mimeType);
                    static::assertFalse($params->deduplicate);

                    return true;
                })
            )
            ->willReturn($mediaId);

        $this->controller->upload($request, $context);
    }

    public function testBuildMediaUploadParamsFromRequestWithEmptyRequest(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $mediaId = Uuid::randomHex();
        $this->mediaUploadService
            ->expects($this->once())
            ->method('uploadFromRequest')
            ->with(
                $request,
                $context,
                static::callback(function (MediaUploadParameters $params) {
                    static::assertNull($params->id);
                    static::assertNull($params->fileName);
                    static::assertNull($params->private);
                    static::assertNull($params->mediaFolderId);
                    static::assertNull($params->mimeType);
                    static::assertNull($params->deduplicate);

                    return true;
                })
            )
            ->willReturn($mediaId);

        $this->controller->upload($request, $context);
    }
}
