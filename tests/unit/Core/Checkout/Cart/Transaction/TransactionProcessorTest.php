<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TransactionProcessor::class)]
class TransactionProcessorTest extends TestCase
{
    public function testProcessCreatesATransactionForTheCartTotal(): void
    {
        $context = Generator::generateSalesChannelContext();

        $transactions = (new TransactionProcessor())->process($this->cartWithTotal(100.0), $context);

        static::assertCount(1, $transactions);
        $transaction = $transactions->first();
        static::assertNotNull($transaction);
        static::assertSame(100.0, $transaction->getAmount()->getTotalPrice());
        static::assertSame($context->getPaymentMethod()->getId(), $transaction->getPaymentMethodId());
    }

    public function testProcessMovesTheKeptOrderTransactionToTheCartTotal(): void
    {
        $orderTransaction = new Transaction($this->price(50.0), 'order-payment-method-id');
        $orderTransaction->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct('order-transaction-id'));

        $cart = $this->cartWithTotal(100.0);
        $cart->setBehavior(new CartBehavior([CheckoutPermissions::KEEP_ORDER_TRANSACTION => true]));
        $cart->setTransactions(new TransactionCollection([
            $orderTransaction,
            new Transaction($this->price(50.0), 'cancelled-payment-method-id'),
        ]));

        $transactions = (new TransactionProcessor())->process($cart, Generator::generateSalesChannelContext());

        static::assertCount(1, $transactions);
        $transaction = $transactions->first();
        static::assertNotNull($transaction);
        static::assertSame(100.0, $transaction->getAmount()->getTotalPrice());
        static::assertSame('order-payment-method-id', $transaction->getPaymentMethodId());
        static::assertSame('order-transaction-id', $transaction->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
        static::assertSame(50.0, $orderTransaction->getAmount()->getTotalPrice());
    }

    public function testProcessKeepsNoTransactionWhenTheOrderHasNone(): void
    {
        $cart = $this->cartWithTotal(100.0);
        $cart->setBehavior(new CartBehavior([CheckoutPermissions::KEEP_ORDER_TRANSACTION => true]));

        static::assertCount(0, (new TransactionProcessor())->process($cart, Generator::generateSalesChannelContext()));
    }

    private function cartWithTotal(float $total): Cart
    {
        $cart = new Cart('cart-token');
        $cart->setPrice(new CartPrice($total, $total, $total, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        return $cart;
    }

    private function price(float $total): CalculatedPrice
    {
        return new CalculatedPrice($total, $total, new CalculatedTaxCollection(), new TaxRuleCollection());
    }
}
