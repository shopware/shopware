<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SyncPaymentTransactionStruct::class)]
class SyncPaymentTransactionStructTest extends TestCase
{
    public function testGetters(): void
    {
        $transaction = new OrderTransactionEntity();
        $order = new OrderEntity();
        $recurring = new RecurringDataStruct('foo', new \DateTime());

        $struct = new SyncPaymentTransactionStruct($transaction, $order, $recurring);

        static::assertSame($transaction, $struct->getOrderTransaction());
        static::assertSame($order, $struct->getOrder());
        static::assertSame($recurring, $struct->getRecurring());
        static::assertTrue($struct->isRecurring());
    }
}
