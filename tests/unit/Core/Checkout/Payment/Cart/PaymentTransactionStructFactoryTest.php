<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\AbstractPaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentTransactionStructFactory::class)]
class PaymentTransactionStructFactoryTest extends TestCase
{
    public function testDecorated(): void
    {
        static::expectException(DecorationPatternException::class);

        $factory = new PaymentTransactionStructFactory();
        $factory->getDecorated();
    }

    public function testDecoration(): void
    {
        $factory = new class extends PaymentTransactionStructFactory {
            public function getDecorated(): AbstractPaymentTransactionStructFactory
            {
                return new static();
            }
        };

        static::assertInstanceOf(PaymentTransactionStructFactory::class, $factory->getDecorated());

        $struct = $factory->build('transaction-id', Context::createDefaultContext(), 'https://return.url');

        static::assertSame('transaction-id', $struct->getOrderTransactionId());
        static::assertSame('https://return.url', $struct->getReturnUrl());
    }

    public function testBuild(): void
    {
        $factory = new PaymentTransactionStructFactory();
        $struct = $factory->build('transaction-id', Context::createDefaultContext(), 'https://return.url');

        static::assertSame('transaction-id', $struct->getOrderTransactionId());
        static::assertSame('https://return.url', $struct->getReturnUrl());
    }

    public function testRefund(): void
    {
        $factory = new PaymentTransactionStructFactory();
        $struct = $factory->refund('refund-id', 'transaction-id');

        static::assertSame('refund-id', $struct->getRefundId());
        static::assertSame('transaction-id', $struct->getOrderTransactionId());
    }
}
