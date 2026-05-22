<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\State;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[CoversClass(Service::class)]
class ServiceTest extends TestCase
{
    public function testFromApp(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $updatedAt = new \DateTimeImmutable('2026-01-02 12:00:00');
        $app = AppFixture::createAppEntity(name: 'MyService', id: 'service-id');
        $app->setLabel('My service');
        $app->setTranslated([
            'description' => 'My translated description',
        ]);
        $app->setIcon('service-icon');
        $app->setCreatedAt($createdAt);
        $app->setUpdatedAt($updatedAt);
        $app->setRequestedPrivileges(['product:read', 'order:read']);
        $app->setAllowedHosts(['https://example.com', 'https://cdn.example.com']);
        $app->setSourceConfig(['requirements' => ['shopware_account', 'service_consent']]);

        $aclRole = new AclRoleEntity();
        $aclRole->setPrivileges(['product:read', 'customer:read']);
        $app->setAclRole($aclRole);

        $service = Service::fromApp($app);

        static::assertSame('service-id', $service->getId());
        static::assertSame('MyService', $service->getName());
        static::assertSame('My service', $service->getLabel());
        static::assertTrue($service->isActive());
        static::assertSame('service-icon', $service->getIcon());
        static::assertSame('My translated description', $service->getDescription());
        static::assertSame($updatedAt, $service->getUpdatedAt());
        static::assertSame('1.0.0', $service->getVersion());
        static::assertSame('acl-role-id', $service->getAclRoleId());
        static::assertSame(['product:read', 'order:read'], $service->getRequestedPrivileges());
        static::assertSame(['product:read', 'customer:read'], $service->getPrivileges());
        static::assertSame(['product:read', 'order:read', 'customer:read'], $service->getAllPrivileges());
        static::assertSame(State::PENDING_PERMISSIONS, $service->getState());
        static::assertSame(['https://example.com', 'https://cdn.example.com'], $service->getDomains());
        static::assertSame(['shopware_account', 'service_consent'], $service->getRequirements());
    }

    public function testFromAppUsesCreatedAtWhenUpdatedAtIsMissing(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyService', active: false);

        $service = Service::fromApp($app);

        static::assertNull($service->getDescription());
        static::assertSame($app->getCreatedAt(), $service->getUpdatedAt());
        static::assertSame([], $service->getPrivileges());
        static::assertSame([], $service->getDomains());
        static::assertSame([], $service->getRequirements());
        static::assertSame(State::INACTIVE, $service->getState());
    }
}
