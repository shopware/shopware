<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AbstractPaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
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
        $factory = new class() extends PaymentTransactionStructFactory {
            public function getDecorated(): AbstractPaymentTransactionStructFactory
            {
                return new static();
            }
        };

        static::assertInstanceOf(PaymentTransactionStructFactory::class, $factory->getDecorated());

        $transaction = new OrderTransactionEntity();
        $order = new OrderEntity();

        $struct = $factory->sync($transaction, $order);

        static::assertSame($transaction, $struct->getOrderTransaction());
        static::assertSame($order, $struct->getOrder());
    }

    public function testSync(): void
    {
        $factory = new PaymentTransactionStructFactory();

        $transaction = new OrderTransactionEntity();
        $order = new OrderEntity();

        $struct = $factory->sync($transaction, $order);

        static::assertSame($transaction, $struct->getOrderTransaction());
        static::assertSame($order, $struct->getOrder());
    }

    public function testAsync(): void
    {
        $factory = new PaymentTransactionStructFactory();

        $transaction = new OrderTransactionEntity();
        $order = new OrderEntity();
        $returnUrl = 'https://return.url';

        $struct = $factory->async($transaction, $order, $returnUrl);

        static::assertSame($transaction, $struct->getOrderTransaction());
        static::assertSame($order, $struct->getOrder());
        static::assertSame($returnUrl, $struct->getReturnUrl());
    }

    public function testPrepared(): void
    {
        $factory = new PaymentTransactionStructFactory();

        $transaction = new OrderTransactionEntity();
        $order = new OrderEntity();

        $struct = $factory->prepared($transaction, $order);

        static::assertSame($transaction, $struct->getOrderTransaction());
        static::assertSame($order, $struct->getOrder());
    }

    public function testRecurring(): void
    {
        $factory = new PaymentTransactionStructFactory();

        $transaction = new OrderTransactionEntity();
        $order = new OrderEntity();

        $struct = $factory->recurring($transaction, $order);

        static::assertSame($transaction, $struct->getOrderTransaction());
        static::assertSame($order, $struct->getOrder());
    }
}
