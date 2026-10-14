<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Api\Acl\AclRoles;
use Shopware\Core\Framework\Api\Acl\ApiCallerType;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PrivilegePolicy;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PrivilegePolicy::class)]
class PrivilegePolicyTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            'acl_role.written' => 'reset',
            AppPermissionsUpdated::class => 'reset',
        ], PrivilegePolicy::getSubscribedEvents());
    }

    public function testHandlesEverySubscription(): void
    {
        $policy = new PrivilegePolicy(static::createStub(AclRoleLoader::class));

        static::assertTrue($policy->handles('product.written'));
        static::assertTrue($policy->permitsSubscription('product.written', Subscriber::api(ApiCallerType::User)));
    }

    public function testUnrestrictedRolesAreNotChecked(): void
    {
        $loader = $this->createMock(AclRoleLoader::class);
        $loader->expects($this->never())->method('getPrivilegesForRoles');

        $event = $this->createMock(Hookable::class);
        $event->expects($this->never())->method('isAllowed');

        static::assertTrue((new PrivilegePolicy($loader))->permitsDelivery($event, $this->webhook(appId: null, roleIds: [], unrestricted: true)));
    }

    public function testTheEventDecidesWithThePrivilegesOfTheOwnersRoles(): void
    {
        $loader = static::createStub(AclRoleLoader::class);
        $loader->method('getPrivilegesForRoles')->willReturn(['role-id' => new AclPrivilegeCollection(['product:read'])]);

        $event = $this->createMock(Hookable::class);
        $event->expects($this->once())
            ->method('isAllowed')
            ->with('app-id', static::callback(static fn (AclPrivilegeCollection $privileges): bool => $privileges->isAllowed('product', 'read')))
            ->willReturn(false);

        static::assertFalse((new PrivilegePolicy($loader))->permitsDelivery($event, $this->webhook(appId: 'app-id', roleIds: ['role-id'])));
    }

    public function testAWebhookWithoutAnAppIsAlsoChecked(): void
    {
        $loader = static::createStub(AclRoleLoader::class);
        $loader->method('getPrivilegesForRoles')->willReturn([]);

        $event = $this->createMock(Hookable::class);
        $event->expects($this->once())
            ->method('isAllowed')
            ->with(Hookable::NO_APP_ID, static::anything())
            ->willReturn(false);

        static::assertFalse((new PrivilegePolicy($loader))->permitsDelivery($event, $this->webhook(appId: null, roleIds: ['role-id'])));
    }

    public function testThePrivilegesOfAllTheOwnersRolesAreMerged(): void
    {
        $loader = static::createStub(AclRoleLoader::class);
        $loader->method('getPrivilegesForRoles')->willReturn([
            'role-a' => new AclPrivilegeCollection(['product:read']),
            'role-b' => new AclPrivilegeCollection(['product:read', 'order:read']),
        ]);

        $event = $this->createMock(Hookable::class);
        $event->expects($this->once())
            ->method('isAllowed')
            ->with('app-id', new AclPrivilegeCollection(['product:read', 'order:read']))
            ->willReturn(true);

        (new PrivilegePolicy($loader))->permitsDelivery($event, $this->webhook(appId: 'app-id', roleIds: ['role-a', 'role-b']));
    }

    public function testAnOwnerWithoutRolesHasNoPrivileges(): void
    {
        $loader = $this->createMock(AclRoleLoader::class);
        $loader->expects($this->never())->method('getPrivilegesForRoles');

        $event = $this->createMock(Hookable::class);
        $event->expects($this->once())
            ->method('isAllowed')
            ->with('app-id', static::callback(static fn (AclPrivilegeCollection $privileges): bool => !$privileges->isAllowed('product', 'read')))
            ->willReturn(true);

        static::assertTrue((new PrivilegePolicy($loader))->permitsDelivery($event, $this->webhook(appId: 'app-id', roleIds: [])));
    }

    public function testRolePrivilegesAreLoadedOnceUntilReset(): void
    {
        $loader = $this->createMock(AclRoleLoader::class);
        $loader->expects($this->exactly(2))
            ->method('getPrivilegesForRoles')
            ->with(['role-id'])
            ->willReturn(['role-id' => new AclPrivilegeCollection(['product:read'])]);

        $event = static::createStub(Hookable::class);
        $event->method('isAllowed')->willReturn(true);

        $policy = new PrivilegePolicy($loader);
        $webhook = $this->webhook(appId: 'app-id', roleIds: ['role-id']);

        $policy->permitsDelivery($event, $webhook);
        $policy->permitsDelivery($event, $webhook);
        $policy->reset();
        $policy->permitsDelivery($event, $webhook);
    }

    /**
     * @param list<string> $roleIds
     */
    private function webhook(?string $appId, array $roleIds, bool $unrestricted = false): Webhook
    {
        return new Webhook(
            id: 'webhook-id',
            webhookName: 'hook',
            eventName: 'product.written',
            url: 'https://example.com',
            onlyLiveVersion: false,
            appId: $appId,
            appName: $appId === null ? null : 'app',
            appSourceType: null,
            appActive: true,
            appVersion: null,
            appSecret: null,
            roles: new AclRoles($unrestricted, $roleIds),
        );
    }
}
