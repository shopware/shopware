<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Event\WebhookDegradedEvent;
use Shopware\Core\Framework\Webhook\Health\EndpointState;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookDegradedEvent::class)]
class WebhookDegradedEventTest extends TestCase
{
    public function testPayloadCarriesIdsNamesStateAndTimesOnlyNeverTheUrl(): void
    {
        $occurredAt = new \DateTimeImmutable('2026-06-02 08:30:00');
        $event = new WebhookDegradedEvent('wh-id', 'app-id', EndpointState::Healthy, 'order-sync', 'checkout.order.placed', $occurredAt);

        $payload = $event->getWebhookPayload();

        static::assertSame('wh-id', $payload['webhookId']);
        static::assertSame('healthy', $payload['fromState']);
        static::assertSame('order-sync', $payload['webhookName']);
        static::assertSame('checkout.order.placed', $payload['eventName']);
        static::assertSame($occurredAt->format(\DateTimeInterface::ATOM), $payload['occurredAt']);
        static::assertArrayNotHasKey('url', $payload);
        foreach ($payload as $value) {
            static::assertTrue($value === null || \is_string($value));
        }
    }

    public function testAvailableDataDeclaresExactlyThePayloadKeys(): void
    {
        $event = new WebhookDegradedEvent('wh-id', 'app-id', EndpointState::Healthy, 'order-sync', 'checkout.order.placed', new \DateTimeImmutable('2026-06-02 08:30:00'));

        $payloadKeys = array_keys($event->getWebhookPayload());
        $availableKeys = array_keys(WebhookDegradedEvent::getAvailableData()->toArray());
        sort($payloadKeys);
        sort($availableKeys);

        static::assertSame($payloadKeys, $availableKeys);
    }
}
