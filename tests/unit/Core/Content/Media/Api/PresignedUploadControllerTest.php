<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\PresignedUploadController;
use Shopware\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopware\Core\Content\Media\Upload\PresignedUploadFinalizePayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadPreparePayload;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PresignedUploadController::class)]
class PresignedUploadControllerTest extends TestCase
{
    private PresignedMediaUploadService&MockObject $service;

    private PresignedUploadController $controller;

    protected function setUp(): void
    {
        $this->service = $this->createMock(PresignedMediaUploadService::class);
        $this->controller = new PresignedUploadController($this->service);
    }

    public function testPrepareReturnsPresignedUrl(): void
    {
        $context = Context::createDefaultContext();
        $payload = new PresignedUploadPreparePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            mediaFolderId: 'folder-123',
            private: false,
        );

        $this->service->expects($this->once())
            ->method('prepare')
            ->with($payload, $context)
            ->willReturn([
                'mediaId' => 'media-id-123',
                'url' => 'https://s3.example.com/presigned-url',
                'path' => 'media/ab/cd/test-file.jpg',
                'expiresAt' => '2026-02-10T12:00:00+00:00',
            ]);

        $response = $this->controller->prepare($payload, $context);

        static::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        static::assertSame('media-id-123', $data['mediaId']);
        static::assertSame('https://s3.example.com/presigned-url', $data['url']);
    }

    public function testFinalizeReturnsMediaId(): void
    {
        $context = Context::createDefaultContext();
        $mediaId = 'media-id-123';
        $payload = new PresignedUploadFinalizePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            path: 'media/ab/cd/test-file.jpg',
        );

        $this->service->expects($this->once())
            ->method('finalize')
            ->with($mediaId, $payload, $context);

        $response = $this->controller->finalize($mediaId, $payload, $context);

        static::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        static::assertSame('media-id-123', $data['mediaId']);
    }
}
