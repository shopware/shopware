<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Event\RecurringPaymentOrderCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 *
 * @deprecated tag:v6.7.0 - will be removed with new payment handlers
 */
#[CoversClass(RecurringPaymentOrderCriteriaEvent::class)]
class RecurringPaymentOrderCriteriaEventTest extends TestCase
{
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testEvent(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $event = new RecurringPaymentOrderCriteriaEvent($orderId, $criteria, $context);

        static::assertSame($orderId, $event->getOrderId());
        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($context, $event->getContext());
    }
}
