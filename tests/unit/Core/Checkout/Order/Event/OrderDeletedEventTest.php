<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Event\OrderDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderDeletedEvent::class)]
class OrderDeletedEventTest extends TestCase
{
    public function testWebhookPayloadContractExposesDeletionSnapshot(): void
    {
        static::assertSame([
            'orderId' => ['type' => 'string'],
            'orderNumber' => ['type' => 'string'],
            'deletedAt' => ['type' => 'string'],
        ], OrderDeletedEvent::getAvailableData()->toArray());
    }

    public function testDeletedOrderIsNotEntityAwareSoFlowsCannotLoadRemovedRows(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new OrderDeletedEvent($context, $orderId, '2024-01-01T00:00:00+00:00', '10001');

        static::assertSame(OrderDeletedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($orderId, $event->getOrderId());
        static::assertSame('10001', $event->getOrderNumber());
        static::assertSame('2024-01-01T00:00:00+00:00', $event->getDeletedAt());
        static::assertSame([
            'orderId' => $orderId,
            'orderNumber' => '10001',
            'deletedAt' => '2024-01-01T00:00:00+00:00',
        ], $event->getValues());
    }
}
