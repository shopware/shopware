<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Policy\Policy;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PolicyRegistry::class)]
class PolicyRegistryTest extends TestCase
{
    public function testEventWithoutAPolicyIsPermitted(): void
    {
        $registry = new PolicyRegistry([new StubPolicy(['foo.event'], permits: false)]);

        static::assertTrue($registry->permitsDelivery(new StubHookable('bar.event'), $this->createWebhook()));
        static::assertTrue($registry->permitsSubscription('bar.event', Subscriber::user()));
    }

    public function testASinglePolicyCanVeto(): void
    {
        $registry = new PolicyRegistry([new StubPolicy(['foo.event'], permits: false)]);

        static::assertFalse($registry->permitsDelivery(new StubHookable(), $this->createWebhook()));
        static::assertFalse($registry->permitsSubscription('foo.event', Subscriber::user()));
    }

    public function testEveryPolicyMustPermit(): void
    {
        $registry = new PolicyRegistry([
            new StubPolicy(['foo.event'], permits: true),
            new StubPolicy(['foo.event'], permits: false),
        ]);

        static::assertFalse($registry->permitsDelivery(new StubHookable(), $this->createWebhook()));
        static::assertFalse($registry->permitsSubscription('foo.event', Subscriber::user()));
    }

    public function testAllPermittingMeansPermitted(): void
    {
        $registry = new PolicyRegistry([
            new StubPolicy(['foo.event'], permits: true),
            new StubPolicy(['foo.event'], permits: true),
        ]);

        static::assertTrue($registry->permitsDelivery(new StubHookable(), $this->createWebhook()));
    }

    public function testAPolicyCanClaimSeveralEvents(): void
    {
        $registry = new PolicyRegistry([new StubPolicy(['foo.event', 'bar.event'], permits: false)]);

        static::assertFalse($registry->permitsDelivery(new StubHookable(), $this->createWebhook()));
        static::assertFalse($registry->permitsDelivery(new StubHookable('bar.event'), $this->createWebhook()));
    }

    public function testAPolicyIsOnlyConsultedForTheEventsItHandles(): void
    {
        $policy = new StubPolicy(['foo.event'], permits: false);

        $registry = new PolicyRegistry([$policy]);
        $registry->permitsDelivery(new StubHookable('bar.event'), $this->createWebhook());
        $registry->permitsSubscription('bar.event', Subscriber::user());

        static::assertSame(0, $policy->permitCalls);
    }

    private function createWebhook(): Webhook
    {
        return new Webhook(
            id: 'webhook-id',
            webhookName: 'hook',
            eventName: 'foo.event',
            url: 'https://example.com',
            onlyLiveVersion: false,
            appId: 'app-id',
            appName: 'SwagApp',
            appSourceType: null,
            appActive: true,
            appVersion: null,
            appSecret: null,
            appAclRoleId: null,
        );
    }
}

/**
 * @internal
 */
#[Package('framework')]
class StubPolicy implements Policy
{
    public int $permitCalls = 0;

    /**
     * @param list<string> $eventNames
     */
    public function __construct(
        private readonly array $eventNames,
        private readonly bool $permits,
    ) {
    }

    public function handles(string $eventName): bool
    {
        return \in_array($eventName, $this->eventNames, true);
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        ++$this->permitCalls;

        return $this->permits;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        ++$this->permitCalls;

        return $this->permits;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class StubHookable implements Hookable
{
    public function __construct(private readonly string $name = 'foo.event')
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
