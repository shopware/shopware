<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Event\WebhookDegradedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookHealthEventBehaviour;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\SuspensionCause;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookSuspendedEvent::class)]
#[CoversTrait(WebhookHealthEventBehaviour::class)]
class WebhookLifecycleEventsTest extends TestCase
{
    public function testPayloadsCarryIdsNamesStateAndTimesOnlyNeverTheUrl(): void
    {
        $since = new \DateTimeImmutable('2026-06-01 12:00:00');
        $occurredAt = new \DateTimeImmutable('2026-06-02 08:30:00');
        $events = [
            new WebhookActivatedEvent('wh-id', 'app-id', EndpointState::Degraded, WebhookActivationTrigger::Trial, 'order-sync', 'checkout.order.placed', $occurredAt, $since),
            new WebhookDegradedEvent('wh-id', 'app-id', EndpointState::Healthy, 'order-sync', 'checkout.order.placed', $occurredAt),
            new WebhookSuspendedEvent('wh-id', 'app-id', EndpointState::Healthy, $since, SuspensionCause::AuthStreak, 'order-sync', 'checkout.order.placed', $occurredAt),
            new WebhookDisabledEvent('wh-id', 'app-id', EndpointState::Suspended, DisabledOrigin::Escalation, 'order-sync', 'checkout.order.placed', $occurredAt),
        ];

        $payloads = [];
        foreach ($events as $event) {
            $payloads[] = $event->getWebhookPayload();
        }

        foreach ($payloads as $payload) {
            static::assertSame('wh-id', $payload['webhookId']);
            static::assertSame('order-sync', $payload['webhookName']);
            static::assertSame('checkout.order.placed', $payload['eventName']);
            static::assertSame($occurredAt->format(\DateTimeInterface::ATOM), $payload['occurredAt']);
            static::assertArrayNotHasKey('url', $payload);
            foreach ($payload as $value) {
                static::assertTrue($value === null || \is_string($value));
            }
        }

        static::assertSame('trial', $payloads[0]['trigger']);
        static::assertSame($since->format(\DateTimeInterface::ATOM), $payloads[2]['suspendedSince']);
        static::assertSame('auth_streak', $payloads[2]['cause']);
        static::assertSame('escalation', $payloads[3]['origin']);
    }

    public function testOnlyTheOwningAppMaySeeItsEndpointsHealth(): void
    {
        $event = $this->suspendedEvent('wh-id', 'owner-app');
        $permissions = new AclPrivilegeCollection([]);

        static::assertTrue($event->isAllowed('owner-app', $permissions));
        static::assertFalse($event->isAllowed('another-app', $permissions));
        static::assertFalse($this->suspendedEvent('wh-id', null)->isAllowed('any-app', $permissions));
    }

    public function testSharedDataContract(): void
    {
        $event = $this->suspendedEvent('wh-id', 'owner-app');

        static::assertSame('wh-id', $event->getWebhookId());
        static::assertSame(EndpointState::Healthy->value, $event->getFromState());
        static::assertSame('order-sync', $event->getWebhookName());
        static::assertSame('checkout.order.placed', $event->getEventName());
        static::assertSame('2026-06-01T12:00:00+00:00', $event->getOccurredAt());
        static::assertInstanceOf(SystemSource::class, $event->getContext()->getSource());
    }

    private function suspendedEvent(string $webhookId, ?string $appId): WebhookSuspendedEvent
    {
        return new WebhookSuspendedEvent(
            $webhookId,
            $appId,
            EndpointState::Healthy,
            new \DateTimeImmutable('2026-06-01T12:00:00+00:00'),
            SuspensionCause::AuthStreak,
            'order-sync',
            'checkout.order.placed',
            new \DateTimeImmutable('2026-06-01T12:00:00+00:00'),
        );
    }
}
