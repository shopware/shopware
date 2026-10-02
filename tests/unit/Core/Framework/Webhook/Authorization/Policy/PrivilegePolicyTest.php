<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PrivilegePolicy;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
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
        $policy = new PrivilegePolicy(static::createStub(WebhookLoader::class));

        static::assertTrue($policy->handles('product.written'));
        static::assertTrue($policy->permitsSubscription('product.written', Subscriber::user()));
    }

    public function testWebhooksWithoutAnAppAreNotChecked(): void
    {
        $loader = $this->createMock(WebhookLoader::class);
        $loader->expects($this->never())->method('getPrivilegesForRoles');

        $event = $this->createMock(Hookable::class);
        $event->expects($this->never())->method('isAllowed');

        static::assertTrue((new PrivilegePolicy($loader))->permitsDelivery($event, $this->webhook(appId: null, roleId: null)));
    }

    public function testTheEventDecidesWithThePrivilegesOfTheAppsRole(): void
    {
        $loader = static::createStub(WebhookLoader::class);
        $loader->method('getPrivilegesForRoles')->willReturn(['role-id' => new AclPrivilegeCollection(['product:read'])]);

        $event = $this->createMock(Hookable::class);
        $event->expects($this->once())
            ->method('isAllowed')
            ->with('app-id', static::callback(static fn (AclPrivilegeCollection $privileges): bool => $privileges->isAllowed('product', 'read')))
            ->willReturn(false);

        static::assertFalse((new PrivilegePolicy($loader))->permitsDelivery($event, $this->webhook(appId: 'app-id', roleId: 'role-id')));
    }

    public function testAnAppWithoutARoleHasNoPrivileges(): void
    {
        $loader = $this->createMock(WebhookLoader::class);
        $loader->expects($this->never())->method('getPrivilegesForRoles');

        $event = $this->createMock(Hookable::class);
        $event->expects($this->once())
            ->method('isAllowed')
            ->with('app-id', static::callback(static fn (AclPrivilegeCollection $privileges): bool => !$privileges->isAllowed('product', 'read')))
            ->willReturn(true);

        static::assertTrue((new PrivilegePolicy($loader))->permitsDelivery($event, $this->webhook(appId: 'app-id', roleId: null)));
    }

    public function testRolePrivilegesAreLoadedOnceUntilReset(): void
    {
        $loader = $this->createMock(WebhookLoader::class);
        $loader->expects($this->exactly(2))
            ->method('getPrivilegesForRoles')
            ->with(['role-id'])
            ->willReturn(['role-id' => new AclPrivilegeCollection(['product:read'])]);

        $event = static::createStub(Hookable::class);
        $event->method('isAllowed')->willReturn(true);

        $policy = new PrivilegePolicy($loader);
        $webhook = $this->webhook(appId: 'app-id', roleId: 'role-id');

        $policy->permitsDelivery($event, $webhook);
        $policy->permitsDelivery($event, $webhook);
        $policy->reset();
        $policy->permitsDelivery($event, $webhook);
    }

    private function webhook(?string $appId, ?string $roleId): Webhook
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
            appAclRoleId: $roleId,
        );
    }
}
