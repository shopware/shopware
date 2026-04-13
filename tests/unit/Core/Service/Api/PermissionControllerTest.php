<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\Api\PermissionController;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\ServiceException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(PermissionController::class)]
class PermissionControllerTest extends TestCase
{
    private PermissionsService&MockObject $permissionsService;

    private PermissionController $controller;

    private Context $context;

    protected function setUp(): void
    {
        $this->permissionsService = $this->createMock(PermissionsService::class);
        $this->controller = new PermissionController($this->permissionsService);
        $this->context = Context::createDefaultContext();
    }

    public function testGrantPermissionsSuccess(): void
    {
        $revision = '2025-06-13';

        $this->permissionsService
            ->expects($this->once())
            ->method('grant')
            ->with($revision, $this->context);

        $response = $this->controller->grantPermissions($revision, $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{}', $response->getContent());
    }

    public function testGrantPermissionsWithInvalidRevision(): void
    {
        $invalidRevision = 'invalid-date';
        $expectedException = ServiceException::invalidPermissionsRevisionFormat($invalidRevision);

        $this->permissionsService
            ->expects($this->once())
            ->method('grant')
            ->with($invalidRevision, $this->context)
            ->willThrowException($expectedException);

        $this->expectExceptionObject($expectedException);

        $this->controller->grantPermissions($invalidRevision, $this->context);
    }

    public function testRevokePermissionsSuccess(): void
    {
        $this->permissionsService
            ->expects($this->once())
            ->method('revoke')
            ->with($this->context);

        $response = $this->controller->revokePermissions($this->context);

        static::assertSame('{}', $response->getContent());
    }

    public function testRevokePermissionsWithServiceException(): void
    {
        $expectedException = new \RuntimeException('Service error');

        $this->permissionsService
            ->expects($this->once())
            ->method('revoke')
            ->with($this->context)
            ->willThrowException($expectedException);

        $this->expectExceptionObject($expectedException);

        $this->controller->revokePermissions($this->context);
    }
}
