<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\PermissionLifecycleService;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(PermissionLifecycleService::class)]
class PermissionLifecycleServiceTest extends TestCase
{
    private Connection&MockObject $connection;

    private Privileges&MockObject $permissions;

    private PermissionLifecycleService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->permissions = $this->createMock(Privileges::class);
        $this->service = new PermissionLifecycleService($this->connection, $this->permissions, new NativeClock());
    }

    public function testUpdatePrivilegesAutoAcceptsIfFlagIsSpecified(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $permissions = Permissions::fromArray(['permissions' => ['customer' => ['read', 'update']]]);

        $this->permissions->expects($this->once())
            ->method('setPrivileges')
            ->with($appId, ['customer:read', 'customer:update'], $context);

        $this->service->updatePrivileges($permissions, $appId, true, $context);
    }

    public function testUpdatePrivilegesDoesNotAutoAcceptIfFlagIsNotSpecified(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $permissions = Permissions::fromArray(['permissions' => ['customer' => ['read', 'update']]]);

        $this->permissions->expects($this->once())
            ->method('requestPrivileges')
            ->with($appId, ['customer:read', 'customer:update'], $context);

        $this->service->updatePrivileges($permissions, $appId, false, Context::createDefaultContext());
    }
}
