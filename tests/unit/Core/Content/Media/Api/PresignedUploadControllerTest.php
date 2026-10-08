<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\PresignedUploadController;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopware\Core\Content\Media\Upload\PresignedUploadConfirmPayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadFinalizePayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadPreparePayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadPrepareResult;
use Shopware\Core\Content\Media\Upload\PresignedUploadTicket;
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
            ->willReturn(new PresignedUploadPrepareResult(
                mediaId: 'media-id-123',
                url: 'https://s3.example.com/presigned-url',
                path: 'media/ab/cd/test-file.jpg',
                expiresAt: '2026-02-10T12:00:00+00:00',
                isDuplicate: false,
            ));

        $response = $this->controller->prepare($payload, $context);

        static::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        static::assertSame('media-id-123', $data['mediaId']);
        static::assertSame('https://s3.example.com/presigned-url', $data['url']);
        static::assertFalse($data['isDuplicate']);
    }

    public function testPrepareReturnsDuplicateFlag(): void
    {
        $context = Context::createDefaultContext();
        $payload = new PresignedUploadPreparePayload(
            fileName: 'duplicate-file',
            extension: 'png',
            mimeType: 'image/png',
            mediaFolderId: 'folder-456',
            private: false,
        );

        $this->service->expects($this->once())
            ->method('prepare')
            ->with($payload, $context)
            ->willReturn(new PresignedUploadPrepareResult(
                mediaId: 'media-id-456',
                url: 'https://s3.example.com/presigned-url-2',
                path: 'media/ab/cd/duplicate-file.png',
                expiresAt: '2026-02-10T12:00:00+00:00',
                isDuplicate: true,
            ));

        $response = $this->controller->prepare($payload, $context);

        static::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        static::assertTrue($data['isDuplicate']);
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

    public function testRequestUploadReturnsTicket(): void
    {
        $context = Context::createDefaultContext();
        $uploadParameters = new MediaUploadParameters(fileName: 'test-file.jpg', mimeType: 'image/jpeg');

        $this->service->expects($this->once())
            ->method('requestUpload')
            ->with($uploadParameters, $context)
            ->willReturn(new PresignedUploadTicket(
                mediaId: 'media-id-123',
                uploadToken: 'signed.token',
                method: 'PUT',
                url: 'https://s3.example.com/presigned-url',
                headers: ['Content-Type' => 'image/jpeg'],
                expiresAt: '2026-02-10T12:00:00+00:00',
            ));

        $ticketResponse = $this->controller->requestUpload($uploadParameters, $context);

        static::assertSame(201, $ticketResponse->getStatusCode());

        $ticket = json_decode((string) $ticketResponse->getContent(), true);
        static::assertSame('media-id-123', $ticket['id']);
        static::assertSame('signed.token', $ticket['uploadToken']);
        static::assertSame('https://s3.example.com/presigned-url', $ticket['upload']['url']);
        static::assertSame(['Content-Type' => 'image/jpeg'], $ticket['upload']['headers']);
    }

    public function testConfirmUploadReturnsMediaId(): void
    {
        $context = Context::createDefaultContext();
        $payload = new PresignedUploadConfirmPayload(uploadToken: 'signed.token');

        $this->service->expects($this->once())
            ->method('confirmUpload')
            ->with($payload, $context)
            ->willReturn('media-id-123');

        $confirmationResponse = $this->controller->confirmUpload($payload, $context);

        static::assertSame(200, $confirmationResponse->getStatusCode());

        $confirmation = json_decode((string) $confirmationResponse->getContent(), true);
        static::assertSame('media-id-123', $confirmation['id']);
    }
}
