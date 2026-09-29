<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Checkout\Cart\Validator;
use Shopware\Core\Checkout\PriceModifier\PriceModifier;
use Shopware\Core\Checkout\PriceModifier\PriceModifierAbsolutePriceDefinition;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCalculatedPrice;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifierResult;
use Shopware\Core\Checkout\PriceModifier\PriceProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * Covers Processor::applyPriceModifiers()'s own orchestration of the shopware.cart.price_processor
 * tagged_iterator -- the no-op fast path, wiring a processor's $additionalCosts/$modifiers through to
 * Cart, and the taxExemptAdjustment capping/uncapped-surcharge behavior -- as opposed to any single
 * PriceProcessorInterface implementation's own math (see OrderPriceModificationProcessorTest for
 * that). AmountCalculator is mocked so the assertions target Processor's own wiring, not
 * AmountCalculator's arithmetic, which has its own coverage elsewhere.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Processor::class)]
class ProcessorTest extends TestCase
{
    private Validator $validator;

    private TransactionProcessor $transactionProcessor;

    private ScriptExecutor $executor;

    private CashRounding $rounding;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->validator = static::createStub(Validator::class);
        $this->validator->method('validate')->willReturn([]);

        $this->transactionProcessor = static::createStub(TransactionProcessor::class);
        $this->transactionProcessor->method('process')->willReturn(new TransactionCollection());

        $this->executor = static::createStub(ScriptExecutor::class);
        $this->rounding = new CashRounding();

        $this->context = Generator::generateSalesChannelContext();
    }

    public function testProcessKeepsPersistedStateOfOriginalCart(): void
    {
        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn(
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS)
        );

        $processor = $this->getProcessor($amountCalculator, []);
        $cart = new Cart('test');

        $calculated = $processor->process($cart, $this->context, new CartBehavior());
        static::assertFalse($calculated->isPersisted());

        $cart->setPersisted(true);

        $calculated = $processor->process($cart, $this->context, new CartBehavior());
        static::assertTrue($calculated->isPersisted());
    }

    public function testNoRecalculationWhenEveryPriceProcessorIsANoOp(): void
    {
        $original = new Cart(Uuid::randomHex());

        $passThroughProcessor = new class implements PriceProcessorInterface {
            public function process(
                CartDataCollection $data,
                Cart $original,
                Cart $toCalculate,
                PriceCollection $prices,
                PriceCollection $shippingCosts,
                PriceCollection $additionalCosts,
                float $taxExemptAdjustment,
                SalesChannelContext $context,
                CartBehavior $behavior
            ): PriceModifierResult {
                return new PriceModifierResult($prices, $shippingCosts);
            }
        };

        $calculatedPrice = new CartPrice(100.0, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS);

        $amountCalculator = $this->createMock(AmountCalculator::class);
        $amountCalculator->expects($this->once())
            ->method('calculate')
            ->willReturn($calculatedPrice);

        $processor = $this->getProcessor($amountCalculator, [$passThroughProcessor]);

        $cart = $processor->process($original, $this->context, new CartBehavior());

        static::assertSame($calculatedPrice, $cart->getPrice());
        static::assertCount(0, $cart->getPriceModifiers());
    }

    public function testAdditionalCostsAndModifiersTriggerRecalculation(): void
    {
        $original = new Cart(Uuid::randomHex());

        $additionalCost = new CalculatedPrice(-10.0, -10.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $modifier = new PriceModifier(
            label: 'Voucher',
            price: new PriceModifierCalculatedPrice(-10.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            priceDefinition: new PriceModifierAbsolutePriceDefinition(-10.0),
            id: Uuid::randomHex(),
        );

        $priceProcessor = new class($additionalCost, $modifier) implements PriceProcessorInterface {
            public function __construct(
                private readonly CalculatedPrice $additionalCost,
                private readonly PriceModifier $modifier,
            ) {
            }

            public function process(
                CartDataCollection $data,
                Cart $original,
                Cart $toCalculate,
                PriceCollection $prices,
                PriceCollection $shippingCosts,
                PriceCollection $additionalCosts,
                float $taxExemptAdjustment,
                SalesChannelContext $context,
                CartBehavior $behavior
            ): PriceModifierResult {
                return new PriceModifierResult(
                    $prices,
                    $shippingCosts,
                    new PriceCollection([$this->additionalCost]),
                    0.0,
                    new PriceModifierCollection([$this->modifier]),
                );
            }
        };

        $priceBeforeModifiers = new CartPrice(100.0, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS);
        $priceAfterModifiers = new CartPrice(90.0, 90.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS);

        $amountCalculator = $this->createMock(AmountCalculator::class);
        $amountCalculator->expects($this->exactly(2))
            ->method('calculate')
            ->willReturnCallback(function (PriceCollection $prices, PriceCollection $shippingCosts, SalesChannelContext $context, ?PriceCollection $additionalCosts = null) use ($priceBeforeModifiers, $priceAfterModifiers, $additionalCost) {
                if ($additionalCosts === null) {
                    return $priceBeforeModifiers;
                }

                static::assertCount(1, $additionalCosts);
                static::assertSame($additionalCost, $additionalCosts->first());

                return $priceAfterModifiers;
            });

        $processor = $this->getProcessor($amountCalculator, [$priceProcessor]);

        $cart = $processor->process($original, $this->context, new CartBehavior());

        static::assertSame($priceAfterModifiers, $cart->getPrice());
        static::assertCount(1, $cart->getPriceModifiers());
        static::assertSame($modifier, $cart->getPriceModifiers()->first());
    }

    public function testNegativeTaxExemptAdjustmentIsCappedAtZeroWithoutTouchingNetOrTax(): void
    {
        $original = new Cart(Uuid::randomHex());

        $priceProcessor = $this->getTaxExemptAdjustmentProcessor(-999.0);

        $calculatedTaxes = new CalculatedTaxCollection([new CalculatedTax(4.0, 19.0, 25.0)]);
        $taxRules = new TaxRuleCollection([new TaxRule(19.0)]);
        $recalculatedPrice = new CartPrice(21.0, 25.0, 25.0, $calculatedTaxes, $taxRules, CartPrice::TAX_STATE_GROSS, 25.0);

        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn($recalculatedPrice);

        $processor = $this->getProcessor($amountCalculator, [$priceProcessor]);

        $price = $processor->process($original, $this->context, new CartBehavior())->getPrice();

        // Reduction is capped so totalPrice/rawTotal can't go below zero -- -999 would otherwise
        // drive both negative.
        static::assertSame(0.0, $price->getTotalPrice());
        static::assertSame(0.0, $price->getRawTotal());
        // netPrice/calculatedTaxes/taxRules stay exactly as AmountCalculator computed them: a
        // taxExemptAdjustment never touches them, by design.
        static::assertSame(21.0, $price->getNetPrice());
        static::assertSame($calculatedTaxes, $price->getCalculatedTaxes());
        static::assertSame($taxRules, $price->getTaxRules());
    }

    public function testPositiveTaxExemptAdjustmentIsAppliedUncapped(): void
    {
        $original = new Cart(Uuid::randomHex());

        $priceProcessor = $this->getTaxExemptAdjustmentProcessor(15.0);

        $recalculatedPrice = new CartPrice(100.0, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS, 100.0);

        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn($recalculatedPrice);

        $processor = $this->getProcessor($amountCalculator, [$priceProcessor]);

        $price = $processor->process($original, $this->context, new CartBehavior())->getPrice();

        // A positive (surcharge) adjustment is applied in full, unlike a reduction it is never capped.
        static::assertSame(115.0, $price->getTotalPrice());
        static::assertSame(115.0, $price->getRawTotal());
        static::assertSame(100.0, $price->getNetPrice());
    }

    public function testTaxExemptAdjustmentIsCashRoundedBeforeBeingAddedToTheTotal(): void
    {
        $original = new Cart(Uuid::randomHex());

        // 0.125 is a genuinely 3-decimal value (exactly representable in binary, so this isn't about
        // IEEE-754 noise -- CartPrice's own constructor already cleans that up via
        // FloatComparator::cast(), independent of this fix). Proves the adjustment is actually
        // rounded to 2 decimals before being added to the total, which cast() alone does not do.
        $priceProcessor = $this->getTaxExemptAdjustmentProcessor(0.125);

        $recalculatedPrice = new CartPrice(10.0, 10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS, 10.0);

        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn($recalculatedPrice);

        $processor = $this->getProcessor($amountCalculator, [$priceProcessor]);

        $price = $processor->process($original, $this->context, new CartBehavior())->getPrice();

        static::assertSame(10.13, $price->getTotalPrice());
        static::assertSame(10.13, $price->getRawTotal());
    }

    public function testEachPriceProcessorReceivesTheTaxExemptAdjustmentOfTheEarlierOnes(): void
    {
        $first = $this->getTaxExemptModifierProcessor(-30.0);
        $second = $this->getTaxExemptModifierProcessor(-20.0);
        $third = $this->getTaxExemptModifierProcessor(-10.0);

        $recalculatedPrice = new CartPrice(100.0, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS, 100.0);
        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn($recalculatedPrice);

        $this->getProcessor($amountCalculator, [$first, $second, $third])->process(new Cart(Uuid::randomHex()), $this->context, new CartBehavior());

        static::assertSame(0.0, $first->receivedTaxExemptAdjustment);
        static::assertSame(-30.0, $second->receivedTaxExemptAdjustment);
        static::assertSame(-50.0, $third->receivedTaxExemptAdjustment);
    }

    public function testCappedTaxExemptReductionsOfIndependentProcessorsAreTrimmedToWhatWasApplied(): void
    {
        // Two processors that each take -80 of a 100 cart without looking at the running state:
        // only 100 can actually be deducted, so the modifiers must add up to -100, not -160.
        $first = $this->getTaxExemptModifierProcessor(-80.0);
        $second = $this->getTaxExemptModifierProcessor(-80.0);

        $recalculatedPrice = new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS, 100.0);
        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn($recalculatedPrice);

        $cart = $this->getProcessor($amountCalculator, [$first, $second])->process(new Cart(Uuid::randomHex()), $this->context, new CartBehavior());

        static::assertSame(0.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(
            [-80.0, -20.0],
            array_values($cart->getPriceModifiers()->map(static fn (PriceModifier $modifier): float => $modifier->getPrice()->getTotalPrice()))
        );
    }

    public function testTrimmingSkipsTaxableModifiersAndKeepsFullyConsumedOnesAtZero(): void
    {
        $taxable = $this->getModifierProcessor(-10.0, taxExempt: false, taxExemptAdjustment: 0.0);
        $first = $this->getTaxExemptModifierProcessor(-60.0);
        $second = $this->getTaxExemptModifierProcessor(-60.0);

        // 50 left after the taxable reduction: -120 tax-exempt has to be trimmed by 70.
        $recalculatedPrice = new CartPrice(42.02, 50.0, 60.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS, 50.0);
        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn($recalculatedPrice);

        $cart = $this->getProcessor($amountCalculator, [$taxable, $first, $second])->process(new Cart(Uuid::randomHex()), $this->context, new CartBehavior());

        static::assertSame(0.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(
            [-10.0, -50.0, 0.0],
            array_values($cart->getPriceModifiers()->map(static fn (PriceModifier $modifier): float => $modifier->getPrice()->getTotalPrice()))
        );
    }

    public function testUncappedTaxExemptReductionsKeepTheirModifiersUntouched(): void
    {
        $processor = $this->getTaxExemptModifierProcessor(-30.0);

        $recalculatedPrice = new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS, 100.0);
        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn($recalculatedPrice);

        $cart = $this->getProcessor($amountCalculator, [$processor])->process(new Cart(Uuid::randomHex()), $this->context, new CartBehavior());

        static::assertSame(70.0, $cart->getPrice()->getTotalPrice());
        static::assertSame($processor->modifier, $cart->getPriceModifiers()->first());
    }

    /**
     * @param iterable<PriceProcessorInterface> $priceProcessors
     */
    private function getProcessor(AmountCalculator $amountCalculator, iterable $priceProcessors): Processor
    {
        return new Processor(
            $this->validator,
            $amountCalculator,
            $this->rounding,
            $this->transactionProcessor,
            [],
            [],
            $this->executor,
            [],
            $priceProcessors,
        );
    }

    private function getTaxExemptAdjustmentProcessor(float $taxExemptAdjustment): PriceProcessorInterface
    {
        return new class($taxExemptAdjustment) implements PriceProcessorInterface {
            public function __construct(
                private readonly float $taxExemptAdjustment,
            ) {
            }

            public function process(
                CartDataCollection $data,
                Cart $original,
                Cart $toCalculate,
                PriceCollection $prices,
                PriceCollection $shippingCosts,
                PriceCollection $additionalCosts,
                float $taxExemptAdjustment,
                SalesChannelContext $context,
                CartBehavior $behavior
            ): PriceModifierResult {
                return new PriceModifierResult($prices, $shippingCosts, new PriceCollection(), $this->taxExemptAdjustment);
            }
        };
    }

    private function getTaxExemptModifierProcessor(float $amount): PriceProcessorStub
    {
        return $this->getModifierProcessor($amount, taxExempt: true, taxExemptAdjustment: $amount);
    }

    private function getModifierProcessor(float $amount, bool $taxExempt, float $taxExemptAdjustment): PriceProcessorStub
    {
        return new PriceProcessorStub(
            new PriceModifier(
                label: 'Voucher',
                price: new PriceModifierCalculatedPrice($amount, new CalculatedTaxCollection(), new TaxRuleCollection()),
                priceDefinition: new PriceModifierAbsolutePriceDefinition($amount, taxExempt: $taxExempt),
                id: Uuid::randomHex(),
            ),
            $taxExemptAdjustment,
        );
    }
}

/**
 * @internal
 */
class PriceProcessorStub implements PriceProcessorInterface
{
    public ?float $receivedTaxExemptAdjustment = null;

    public function __construct(
        public readonly PriceModifier $modifier,
        private readonly float $taxExemptAdjustment,
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        PriceCollection $prices,
        PriceCollection $shippingCosts,
        PriceCollection $additionalCosts,
        float $taxExemptAdjustment,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): PriceModifierResult {
        $this->receivedTaxExemptAdjustment = $taxExemptAdjustment;

        return new PriceModifierResult(
            $prices,
            $shippingCosts,
            taxExemptAdjustment: $this->taxExemptAdjustment,
            modifiers: new PriceModifierCollection([$this->modifier]),
        );
    }
}
