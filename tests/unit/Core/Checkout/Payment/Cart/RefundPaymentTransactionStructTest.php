<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RefundPaymentTransactionStruct::class)]
class RefundPaymentTransactionStructTest extends TestCase
{
    public function testGetters(): void
    {
        $struct = new RefundPaymentTransactionStruct('refund-id', 'transaction-id');

        static::assertSame('refund-id', $struct->getRefundId());
        static::assertSame('transaction-id', $struct->getOrderTransactionId());
        static::assertNull($struct->getReturnUrl());
        static::assertNull($struct->getRecurring());
        static::assertFalse($struct->isRecurring());
    }
}
