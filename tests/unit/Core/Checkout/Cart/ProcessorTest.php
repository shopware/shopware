<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Checkout\Cart\Validator;
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

    private function getProcessor(): Processor
    {
        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn(
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS)
        );

        $transactionProcessor = static::createStub(TransactionProcessor::class);
        $transactionProcessor->method('process')->willReturn(new TransactionCollection());

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
