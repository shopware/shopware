<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentTransactionStruct::class)]
class PaymentTransactionStructTest extends TestCase
{
    public function testGetters(): void
    {
        $recurring = new RecurringDataStruct('foo', new \DateTime());

        $struct = new PaymentTransactionStruct('transaction-id', 'https://return.url', $recurring);

        static::assertSame('transaction-id', $struct->getOrderTransactionId());
        static::assertSame('https://return.url', $struct->getReturnUrl());
        static::assertSame($recurring, $struct->getRecurring());
        static::assertTrue($struct->isRecurring());
    }
}
