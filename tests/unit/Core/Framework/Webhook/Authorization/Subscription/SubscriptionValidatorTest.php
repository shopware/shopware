<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Policy\Policy;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionRefusals;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SubscriptionValidator::class)]
class SubscriptionValidatorTest extends TestCase
{
    private SubscriptionValidator $validator;

    protected function setUp(): void
    {
        $collector = static::createStub(HookableEventCollector::class);
        $collector->method('getHookableEventNamesWithPrivileges')->willReturn([
            'product.written' => ['privileges' => ['product:read', 'order:read']],
            'order.written' => ['privileges' => []],
        ]);

        $this->validator = new SubscriptionValidator($collector, new PolicyRegistry([new RefuseOrderWrittenPolicy()]));
    }

    public function testEmptySubscriptionsAreSkipped(): void
    {
        $collector = $this->createMock(HookableEventCollector::class);
        $collector->expects($this->never())->method('getHookableEventNamesWithPrivileges');

        $refusals = (new SubscriptionValidator($collector, new PolicyRegistry([])))->validate([], null, Subscriber::user(), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }

    public function testUnhookableEventIsRefused(): void
    {
        $refusals = $this->validator->validate(['hook' => 'unknown.event'], [], Subscriber::user(), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(notHookable: ['hook']), $refusals);
    }

    public function testPolicyRefusalIsReported(): void
    {
        $refusals = $this->validator->validate(['hook' => 'order.written'], [], Subscriber::user(), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(notPermitted: ['hook']), $refusals);
    }

    public function testMissingPrivilegesAreReported(): void
    {
        $refusals = $this->validator->validate(['hook' => 'product.written'], ['order:read'], Subscriber::user(), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(missingPrivileges: ['hook' => ['product:read']]), $refusals);
    }

    public function testHeldPrivilegesArePermitted(): void
    {
        $refusals = $this->validator->validate(['hook' => 'product.written'], ['product:read', 'order:read'], Subscriber::user(), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }

    public function testNullPrivilegesHoldEverything(): void
    {
        $refusals = $this->validator->validate(['hook' => 'product.written'], null, Subscriber::user(), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class RefuseOrderWrittenPolicy implements Policy
{
    public function handles(string $eventName): bool
    {
        return $eventName === 'order.written';
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        return false;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        return true;
    }
}
