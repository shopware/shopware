<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Event\OrderUpdatedEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderUpdatedEvent::class)]
class OrderUpdatedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'order' => ['type' => 'entity', 'entityClass' => OrderDefinition::class, 'entityName' => OrderDefinition::ENTITY_NAME],
            'orderId' => ['type' => 'string'],
            'changedFields' => ['type' => 'array', 'of' => ['type' => 'string']],
        ], OrderUpdatedEvent::getAvailableData()->toArray());
    }

    public function testChangedFieldsAreExposedAsDeltaHint(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new OrderUpdatedEvent(
            $context,
            $orderId,
            static fn (): OrderEntity => new OrderEntity(),
            ['amountTotal', 'deliveries.trackingCodes']
        );

        static::assertSame(OrderUpdatedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($orderId, $event->getOrderId());
        static::assertSame(['amountTotal', 'deliveries.trackingCodes'], $event->getChangedFields());
        static::assertSame([
            'orderId' => $orderId,
            'changedFields' => ['amountTotal', 'deliveries.trackingCodes'],
        ], $event->getValues());
    }
}
