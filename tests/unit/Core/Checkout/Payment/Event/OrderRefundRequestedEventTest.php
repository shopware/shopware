<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Event\OrderRefundRequestedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderRefundRequestedEvent::class)]
class OrderRefundRequestedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'refundId' => ['type' => 'string'],
            'orderTransactionId' => ['type' => 'string'],
            'orderId' => ['type' => 'string'],
        ], OrderRefundRequestedEvent::getAvailableData()->toArray());
    }

    public function testEventCarriesRefundData(): void
    {
        $refundId = Uuid::randomHex();
        $orderTransactionId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new OrderRefundRequestedEvent($context, $refundId, $orderTransactionId, $orderId);

        static::assertSame(OrderRefundRequestedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($refundId, $event->getRefundId());
        static::assertSame($orderTransactionId, $event->getOrderTransactionId());
        static::assertSame($orderId, $event->getOrderId());
        static::assertSame([
            'refundId' => $refundId,
            'orderTransactionId' => $orderTransactionId,
            'orderId' => $orderId,
        ], $event->getValues());
    }
}
