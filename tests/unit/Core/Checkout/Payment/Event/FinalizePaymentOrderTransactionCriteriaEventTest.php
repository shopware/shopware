<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Event\FinalizePaymentOrderTransactionCriteriaEvent;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Payment\Event\FinalizePaymentOrderTransactionCriteriaEvent
 */
class FinalizePaymentOrderTransactionCriteriaEventTest extends TestCase
{
    public function testEvent(): void
    {
        $transactionId = Uuid::randomHex();
        $context = Generator::createSalesChannelContext();
        $criteria = new Criteria();

        $event = new FinalizePaymentOrderTransactionCriteriaEvent($transactionId, $criteria, $context);

        static::assertSame($transactionId, $event->getOrderTransactionId());
        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($context, $event->getContext());
    }
}
