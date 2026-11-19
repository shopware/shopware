<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\PresignedUploadController;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopware\Core\Content\Media\Upload\PresignedUploadFinalizePayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadPreparePayload;
use Shopware\Core\Content\Media\Upload\PresignedUploadPrepareResult;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

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

    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresMediaUpdatePrivilege(string $routeName): void
    {
        $this->service->expects($this->never())->method(static::anything());

        $route = (new AttributeRouteControllerLoader())->load(PresignedUploadController::class)->get($routeName);

        static::assertNotNull($route, \sprintf('Route "%s" is not defined on %s', $routeName, PresignedUploadController::class));
        static::assertSame(['media:update'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'presign upload' => ['api.action.media.presign-upload'];
        yield 'finalize upload' => ['api.action.media.finalize-upload'];
    }

    public function testPrepareOfNewMediaRequiresCreatePrivilege(): void
    {
        $context = $this->createIntegrationContext(['media:update']);
        $payload = new PresignedUploadPreparePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
        );

        $this->service->expects($this->never())->method('prepare');

        $this->expectExceptionObject(MediaException::missingPrivileges(['media:create']));

        $this->controller->prepare($payload, $context);
    }

    public function testPrepareOfReplacementDoesNotRequireCreatePrivilege(): void
    {
        $context = $this->createIntegrationContext(['media:update']);
        $mediaId = Uuid::randomHex();
        $payload = new PresignedUploadPreparePayload(
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            mediaId: $mediaId,
        );

        $this->service->expects($this->once())
            ->method('prepare')
            ->with($payload, $context)
            ->willReturn(new PresignedUploadPrepareResult(
                mediaId: $mediaId,
                url: 'https://s3.example.com/presigned-url',
                path: 'media/ab/cd/test-file.jpg',
                expiresAt: '2026-02-10T12:00:00+00:00',
                isDuplicate: false,
            ));

        $response = $this->controller->prepare($payload, $context);

        static::assertSame(200, $response->getStatusCode());
    }

    /**
     * @param list<string> $permissions
     */
    private function createIntegrationContext(array $permissions): Context
    {
        $source = new AdminApiSource(userId: null, integrationId: Uuid::randomHex());
        $source->setPermissions($permissions);

        return Context::createDefaultContext($source);
    }
}
