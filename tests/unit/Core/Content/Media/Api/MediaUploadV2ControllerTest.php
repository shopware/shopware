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

    public function testUploadUrlWithInvalidUrl(): void
    {
        $request = new Request([], ['url' => null]);
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);

        $this->controller->uploadUrl($request, new MediaUploadParameters(), $context);
    }

    public function testUploadUrlWithNonStringUrl(): void
    {
        $request = new Request([], ['url' => ['invalid' => 'array']]);
        $context = Context::createDefaultContext();

        $this->expectException(\TypeError::class);

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

    public function testExternalLinkWithInvalidUrl(): void
    {
        $request = new Request([], ['url' => null]);
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);

        $this->controller->externalLink($request, new MediaUploadParameters(), $context);
    }

    public function testExternalLinkWithNonStringUrl(): void
    {
        $request = new Request([], ['url' => 123]);
        $context = Context::createDefaultContext();

        $this->expectException(\TypeError::class);

        $this->controller->externalLink($request, new MediaUploadParameters(), $context);
    }
}
