<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Event\OrderCreatedEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderCreatedEvent::class)]
class OrderCreatedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'order' => ['type' => 'entity', 'entityClass' => OrderDefinition::class, 'entityName' => OrderDefinition::ENTITY_NAME],
            'orderId' => ['type' => 'string'],
        ], OrderCreatedEvent::getAvailableData()->toArray());
    }

    public function testOrderIsLoadedLazilyAndOnlyOnce(): void
    {
        $orderId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $loads = [];
        $order = new OrderEntity();
        $loader = static function () use (&$loads, $order): OrderEntity {
            $loads[] = true;

            return $order;
        };

        $event = new OrderCreatedEvent($context, $orderId, $loader, $salesChannelId);

        static::assertSame(OrderCreatedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($orderId, $event->getOrderId());
        static::assertSame($salesChannelId, $event->getSalesChannelId());
        static::assertSame(['orderId' => $orderId], $event->getValues());
        static::assertCount(0, $loads, 'payload-known sales channel id must not trigger the lazy load');
        static::assertSame($order, $event->getOrder());
        static::assertSame($order, $event->getOrder());
        static::assertCount(1, $loads);
    }
}
