<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Health\EndpointState;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookActivatedEvent::class)]
class WebhookActivatedEventTest extends TestCase
{
    public function testPayloadCarriesIdsNamesStateTriggerAndTimesOnlyNeverTheUrl(): void
    {
        $since = new \DateTimeImmutable('2026-06-01 12:00:00');
        $occurredAt = new \DateTimeImmutable('2026-06-02 08:30:00');
        $event = new WebhookActivatedEvent('wh-id', 'app-id', EndpointState::Degraded, WebhookActivationTrigger::Trial, 'order-sync', 'checkout.order.placed', $occurredAt, $since);

        $payload = $event->getWebhookPayload();

        static::assertSame('wh-id', $payload['webhookId']);
        static::assertSame('degraded', $payload['fromState']);
        static::assertSame('order-sync', $payload['webhookName']);
        static::assertSame('checkout.order.placed', $payload['eventName']);
        static::assertSame($occurredAt->format(\DateTimeInterface::ATOM), $payload['occurredAt']);
        static::assertSame('trial', $payload['trigger']);
        static::assertSame($since->format(\DateTimeInterface::ATOM), $payload['clearedSuspendedSince']);
        static::assertArrayNotHasKey('url', $payload);
        foreach ($payload as $value) {
            static::assertTrue($value === null || \is_string($value));
        }
    }

    public function testAvailableDataDeclaresExactlyThePayloadKeys(): void
    {
        $event = new WebhookActivatedEvent('wh-id', 'app-id', EndpointState::Degraded, WebhookActivationTrigger::Trial, 'order-sync', 'checkout.order.placed', new \DateTimeImmutable('2026-06-02 08:30:00'));

        $payloadKeys = array_keys($event->getWebhookPayload());
        $availableKeys = array_keys(WebhookActivatedEvent::getAvailableData()->toArray());
        sort($payloadKeys);
        sort($availableKeys);

        static::assertSame($payloadKeys, $availableKeys);
    }
}
