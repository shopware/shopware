<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Checkout\Cart\Validator;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Processor::class)]
class ProcessorTest extends TestCase
{
    public function testProcessKeepsPersistedStateOfOriginalCart(): void
    {
        $processor = $this->getProcessor();
        $context = Generator::generateSalesChannelContext();

        $cart = new Cart('test');

        $calculated = $processor->process($cart, $context, new CartBehavior());
        static::assertFalse($calculated->isPersisted());

        $cart->setPersisted(true);

        $calculated = $processor->process($cart, $context, new CartBehavior());
        static::assertTrue($calculated->isPersisted());
    }

    public function testProcessKeepsTheOrderTransactionOfTheOriginalCart(): void
    {
        $processor = $this->getProcessor(new TransactionProcessor());

        $cart = new Cart('test');
        $cart->setTransactions(new TransactionCollection([
            new Transaction(new CalculatedPrice(50, 50, new CalculatedTaxCollection(), new TaxRuleCollection()), 'order-payment-method-id'),
        ]));

        $calculated = $processor->process(
            $cart,
            Generator::generateSalesChannelContext(),
            new CartBehavior([CheckoutPermissions::KEEP_ORDER_TRANSACTION => true])
        );

        static::assertCount(1, $calculated->getTransactions());
        static::assertSame('order-payment-method-id', $calculated->getTransactions()->first()?->getPaymentMethodId());
    }

    private function getProcessor(?TransactionProcessor $transactionProcessor = null): Processor
    {
        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn(
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS)
        );

        if ($transactionProcessor === null) {
            $transactionProcessor = static::createStub(TransactionProcessor::class);
            $transactionProcessor->method('process')->willReturn(new TransactionCollection());
        }

        return new Processor(
            static::createStub(Validator::class),
            $amountCalculator,
            $transactionProcessor,
            [],
            [],
            static::createStub(ScriptExecutor::class)
        );
    }
}
