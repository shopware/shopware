<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\PriceModifier\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationEntity;
use Shopware\Core\Checkout\PriceModifier\Order\OrderPriceModificationCollector;
use Shopware\Core\Checkout\PriceModifier\Order\OrderPriceModificationProcessor;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCalculatedPrice;
use Shopware\Core\Checkout\PriceModifier\PriceModifierPercentagePriceDefinition;
use Shopware\Core\Checkout\PriceModifier\PriceModifierResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderPriceModificationProcessor::class)]
class OrderPriceModificationProcessorTest extends TestCase
{
    private OrderPriceModificationProcessor $processor;

    private QuantityPriceCalculator $quantityPriceCalculator;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $rounding = new CashRounding();
        $taxCalculator = new TaxCalculator();

        $this->quantityPriceCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $rounding),
            new NetPriceCalculator($taxCalculator, $rounding),
        );

        $this->processor = new OrderPriceModificationProcessor(
            new AbsolutePriceCalculator($this->quantityPriceCalculator, new PercentageTaxRuleBuilder()),
            $this->quantityPriceCalculator,
            new PercentageTaxRuleBuilder(),
            $rounding,
        );

        $this->context = Generator::generateSalesChannelContext();
    }

    public function testNoOpWhenNoModificationsWereCollected(): void
    {
        $prices = $this->priceAt(19, 100.0);
        $shippingCosts = new PriceCollection();

        $result = $this->process(new CartDataCollection(), $prices, $shippingCosts);

        static::assertSame($prices, $result->prices);
        static::assertSame($shippingCosts, $result->shippingCosts);
        static::assertCount(0, $result->modifiers);
        static::assertSame(0.0, $result->additionalCosts->getTotalPriceAmount());
        static::assertSame(0.0, $result->taxExemptAdjustment);
    }

    public function testNoOpWhenModificationCollectionIsEmpty(): void
    {
        $data = new CartDataCollection();
        $data->set(OrderPriceModificationCollector::DATA_KEY, new OrderPriceModificationCollection());

        $result = $this->process($data, $this->priceAt(19, 100.0), new PriceCollection());

        static::assertCount(0, $result->modifiers);
        static::assertSame(0.0, $result->additionalCosts->getTotalPriceAmount());
    }

    public function testTaxableUnrestrictedReductionIsSplitAcrossExistingRates(): void
    {
        $prices = $this->priceAt(19, 100.0);

        $modification = $this->modification(price: -10.0, taxRules: new TaxRuleCollection([]));
        $data = $this->dataWith($modification);

        $result = $this->process($data, $prices, new PriceCollection());

        static::assertCount(1, $result->modifiers);
        static::assertSame(-10.0, $result->additionalCosts->getTotalPriceAmount());
        $modifierPrice = $this->firstModifierPrice($result);
        static::assertSame(-10.0, $modifierPrice->getTotalPrice());
        static::assertCount(1, $modifierPrice->getTaxRules());
        $taxRule = $modifierPrice->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame(19.0, $taxRule->getTaxRate());
    }

    public function testTaxableRestrictedSurchargeUsesRemainingPriceAtThatRate(): void
    {
        $prices = $this->priceAt(19, 100.0)->merge($this->priceAt(7, 50.0));

        $modification = $this->modification(price: -5.0, taxRules: new TaxRuleCollection([new TaxRule(7)]));
        $data = $this->dataWith($modification);

        $result = $this->process($data, $prices, new PriceCollection());

        static::assertCount(1, $result->modifiers);
        $modifierPrice = $this->firstModifierPrice($result);
        static::assertSame(-5.0, $modifierPrice->getTotalPrice());
        static::assertCount(1, $modifierPrice->getTaxRules());
        $taxRule = $modifierPrice->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame(7.0, $taxRule->getTaxRate());
    }

    public function testRestrictedSurchargeWithNoRemainingPriceAtRateFallsBackToExplicitTaxRate(): void
    {
        // F3: only 19% line items exist, but this surcharge is restricted to 7% -- there is nothing
        // left at 7% for AbsolutePriceCalculator to derive a tax rule from.
        $prices = $this->priceAt(19, 100.0);

        $modification = $this->modification(price: 5.0, taxRules: new TaxRuleCollection([new TaxRule(7)]));
        $data = $this->dataWith($modification);

        $result = $this->process($data, $prices, new PriceCollection());

        static::assertCount(1, $result->modifiers);
        $modifierPrice = $this->firstModifierPrice($result);
        static::assertSame(5.0, $modifierPrice->getTotalPrice());
        static::assertCount(1, $modifierPrice->getCalculatedTaxes());
        $calculatedTax = $modifierPrice->getCalculatedTaxes()->first();
        static::assertNotNull($calculatedTax);
        static::assertSame(7.0, $calculatedTax->getTaxRate());
    }

    public function testRestrictedReductionWithNoRemainingPriceAtRateIsCappedToZeroAndSkipped(): void
    {
        // Same "nothing left at this rate" situation as the F3 fallback, but as a reduction: the
        // fallback branch is surcharge-only ($amount > 0.0 guard), so this must be capped away
        // entirely rather than ever reaching it.
        $prices = $this->priceAt(19, 100.0);

        $modification = $this->modification(price: -5.0, taxRules: new TaxRuleCollection([new TaxRule(7)]));
        $data = $this->dataWith($modification);

        $result = $this->process($data, $prices, new PriceCollection());

        static::assertCount(0, $result->modifiers);
        static::assertSame(0.0, $result->additionalCosts->getTotalPriceAmount());
    }

    public function testTaxExemptModificationBypassesTaxCalculationEntirely(): void
    {
        $prices = $this->priceAt(19, 100.0);

        $modification = $this->modification(price: -10.0, taxRules: null);
        $data = $this->dataWith($modification);

        $result = $this->process($data, $prices, new PriceCollection());

        static::assertCount(1, $result->modifiers);
        static::assertSame(-10.0, $result->taxExemptAdjustment);
        static::assertSame(0.0, $result->additionalCosts->getTotalPriceAmount());

        $modifierPrice = $this->firstModifierPrice($result);
        static::assertSame(-10.0, $modifierPrice->getTotalPrice());
        static::assertCount(0, $modifierPrice->getCalculatedTaxes());
        static::assertCount(1, $modifierPrice->getTaxRules());
    }

    public function testTaxExemptReductionIsCappedAgainstRunningEligibleTotal(): void
    {
        $prices = $this->priceAt(19, 100.0);

        $modification = $this->modification(price: -150.0, taxRules: null);
        $data = $this->dataWith($modification);

        $result = $this->process($data, $prices, new PriceCollection());

        static::assertSame(-100.0, $result->taxExemptAdjustment);
    }

    public function testTaxExemptReductionIsCappedAgainstWhatEarlierProcessorsAlreadyDeducted(): void
    {
        // An earlier processor in the chain already took -80 of the 100 tax-exempt: only 20 are left.
        $modification = $this->modification(price: -80.0, taxRules: null);

        $result = $this->process($this->dataWith($modification), $this->priceAt(19, 100.0), new PriceCollection(), taxExemptAdjustment: -80.0);

        // Only this processor's own contribution is returned, not the -80 passed in.
        static::assertSame(-20.0, $result->taxExemptAdjustment);
        static::assertSame(-20.0, $this->firstModifierPrice($result)->getTotalPrice());
    }

    public function testTaxExemptAdjustmentAccumulatesWithoutFloatDrift(): void
    {
        $prices = $this->priceAt(19, 100.0);

        // -0.1 + -0.2 in raw IEEE-754 float arithmetic comes back as -0.30000000000000004, not a
        // clean -0.3, even though both persisted amounts are individually clean -- proves each row's
        // contribution, and the running total, are actually cash-rounded rather than accumulating
        // drift across rows. A tax-exempt amount never passes through AbsolutePriceCalculator/
        // QuantityPriceCalculator (see OrderPriceModificationProcessor::taxExemptPrice()), so nothing
        // else would catch this.
        $first = $this->modification(price: -0.1, taxRules: null, position: 0);
        $second = $this->modification(price: -0.2, taxRules: null, position: 1);
        $data = $this->dataWith($first, $second);

        $result = $this->process($data, $prices, new PriceCollection());

        static::assertSame(-0.3, $result->taxExemptAdjustment);
    }

    public function testPercentageModificationIsRecomputedFromItsDefinitionNotItsCachedPrice(): void
    {
        // The goods doubled since placement: the persisted -10.0 is only the cached value of the
        // frozen -10% at that time, the recalculation must yield -10% of the current goods.
        $prices = $this->priceAt(19, 200.0);

        $modification = $this->modification(
            price: -10.0,
            taxRules: new TaxRuleCollection([]),
            priceDefinition: $this->percentageDefinition(-10.0, lineItems: true, shipping: false),
        );

        $result = $this->process($this->dataWith($modification), $prices, $this->priceAt(19, 10.0));

        static::assertCount(1, $result->modifiers);
        static::assertSame(-20.0, $result->additionalCosts->getTotalPriceAmount());
        static::assertSame(-20.0, $this->firstModifierPrice($result)->getTotalPrice());

        $modifier = $result->modifiers->first();
        static::assertNotNull($modifier);
        static::assertInstanceOf(PriceModifierPercentagePriceDefinition::class, $modifier->getPriceDefinition());
    }

    public function testPercentageModificationTargetingShippingIsSplitAcrossTheShippingRateToo(): void
    {
        $modification = $this->modification(
            price: -5.0,
            taxRules: new TaxRuleCollection([]),
            priceDefinition: $this->percentageDefinition(-10.0, lineItems: true, shipping: true),
        );

        $result = $this->process($this->dataWith($modification), $this->priceAt(19, 100.0), $this->priceAt(7, 50.0));

        static::assertSame(-15.0, $result->additionalCosts->getTotalPriceAmount());

        $taxRates = $this->firstModifierPrice($result)->getTaxRules()->map(static fn (TaxRule $rule): float => $rule->getTaxRate());
        sort($taxRates);
        static::assertSame([7.0, 19.0], $taxRates);
    }

    public function testPercentageModificationTargetingOnlyShippingIgnoresTheGoods(): void
    {
        $modification = $this->modification(
            price: -1.0,
            taxRules: new TaxRuleCollection([]),
            priceDefinition: $this->percentageDefinition(-50.0, lineItems: false, shipping: true),
        );

        $result = $this->process($this->dataWith($modification), $this->priceAt(19, 100.0), $this->priceAt(19, 10.0));

        static::assertSame(-5.0, $result->additionalCosts->getTotalPriceAmount());
    }

    public function testPercentageModificationWithoutTargetDefaultsToGoodsAndShipping(): void
    {
        $modification = $this->modification(
            price: 0.0,
            taxRules: null,
            priceDefinition: ['type' => 'percentage', 'percentage' => -10.0],
        );

        $result = $this->process($this->dataWith($modification), $this->priceAt(19, 100.0), $this->priceAt(19, 10.0));

        static::assertSame(-11.0, $result->taxExemptAdjustment);
    }

    public function testTaxExemptPercentageSurchargeIsRecomputedAndLeavesTaxUntouched(): void
    {
        $modification = $this->modification(
            price: 1.0,
            taxRules: null,
            priceDefinition: $this->percentageDefinition(2.5, lineItems: true, shipping: false),
        );

        $result = $this->process($this->dataWith($modification), $this->priceAt(19, 100.0), new PriceCollection());

        static::assertSame(2.5, $result->taxExemptAdjustment);
        static::assertSame(0.0, $result->additionalCosts->getTotalPriceAmount());
        static::assertCount(0, $this->firstModifierPrice($result)->getCalculatedTaxes());
    }

    public function testPercentageReductionIsCappedLikeAnyOtherReduction(): void
    {
        // An absolute row already consumed most of the goods; -50% of the original goods no longer
        // fits into what remains.
        $absolute = $this->modification(price: -80.0, taxRules: new TaxRuleCollection([]), position: 0);
        $percentage = $this->modification(
            price: -50.0,
            taxRules: new TaxRuleCollection([]),
            position: 1,
            priceDefinition: $this->percentageDefinition(-50.0, lineItems: true, shipping: false),
        );

        $result = $this->process($this->dataWith($absolute, $percentage), $this->priceAt(19, 100.0), new PriceCollection());

        static::assertCount(2, $result->modifiers);
        static::assertSame(-100.0, $result->additionalCosts->getTotalPriceAmount());
    }

    public function testSecondReductionInSamePassIsCappedByWhatTheFirstOneAlreadyConsumed(): void
    {
        $prices = $this->priceAt(19, 100.0);

        $first = $this->modification(price: -60.0, taxRules: new TaxRuleCollection([]), position: 0);
        $second = $this->modification(price: -60.0, taxRules: new TaxRuleCollection([]), position: 1);
        $data = $this->dataWith($first, $second);

        $result = $this->process($data, $prices, new PriceCollection());

        static::assertCount(2, $result->modifiers);
        // The eligible total (100) is fully consumed: -60 by the first row, only -40 left for the
        // second, even though its own persisted amount is -60.
        static::assertSame(-100.0, $result->additionalCosts->getTotalPriceAmount());
    }

    public function testUnrestrictedReductionIsCappedAgainstGoodsOnlyNotGoodsPlusShipping(): void
    {
        // Goods (80) and shipping (20) are both taxed at 19%, so they'd naively look
        // interchangeable -- but an unrestricted row's own eligible base (targetRates/
        // remainingPricesAtRates) is deliberately derived from $prices (goods) alone, shipping is
        // never part of it. A reduction of -90 sits between the goods-only base (80) and the
        // goods+shipping total (100): it must be capped at the actual base it's redistributed
        // across (-80), never at the larger total that includes a collection it's never applied to
        // -- otherwise the resulting goods-at-19% total would go negative even though the cap
        // "looked" safe against the wrong (larger) ceiling.
        $prices = $this->priceAt(19, 80.0);
        $shippingCosts = $this->priceAt(19, 20.0);

        $modification = $this->modification(price: -90.0, taxRules: new TaxRuleCollection([]));
        $data = $this->dataWith($modification);

        $result = $this->process($data, $prices, $shippingCosts);

        static::assertSame(-80.0, $result->additionalCosts->getTotalPriceAmount());
        $modifierPrice = $this->firstModifierPrice($result);
        static::assertSame(-80.0, $modifierPrice->getTotalPrice());
        // Shipping itself is untouched by this row: prices/shippingCosts are returned unmodified,
        // only additionalCosts carries the adjustment.
        static::assertSame($shippingCosts, $result->shippingCosts);
    }

    public function testPayloadAndIdentityArePassedThroughToTheCartModifier(): void
    {
        $modification = $this->modification(price: -10.0, taxRules: null);
        $modification->setDescription('Summer campaign');
        $modification->setType('SwagVoucher');
        $modification->setReferencedId('voucher');
        $modification->setPayload(['code' => 'SUMMER-2026']);

        $result = $this->process($this->dataWith($modification), $this->priceAt(19, 100.0), new PriceCollection());

        $modifier = $result->modifiers->first();
        static::assertNotNull($modifier);
        static::assertSame('Summer campaign', $modifier->getDescription());
        static::assertSame('SwagVoucher', $modifier->getType());
        static::assertSame('voucher', $modifier->getReferencedId());
        static::assertSame(['code' => 'SUMMER-2026'], $modifier->getPayload());
    }

    private function firstModifierPrice(PriceModifierResult $result): PriceModifierCalculatedPrice
    {
        $modifier = $result->modifiers->first();
        static::assertNotNull($modifier);

        return $modifier->getPrice();
    }

    private function process(CartDataCollection $data, PriceCollection $prices, PriceCollection $shippingCosts, float $taxExemptAdjustment = 0.0): PriceModifierResult
    {
        return $this->processor->process(
            $data,
            new Cart('test'),
            new Cart('test'),
            $prices,
            $shippingCosts,
            new PriceCollection(),
            $taxExemptAdjustment,
            $this->context,
            new CartBehavior(),
        );
    }

    private function dataWith(OrderPriceModificationEntity ...$modifications): CartDataCollection
    {
        $data = new CartDataCollection();
        $data->set(OrderPriceModificationCollector::DATA_KEY, new OrderPriceModificationCollection($modifications));

        return $data;
    }

    /**
     * @param array<string, mixed>|null $priceDefinition
     */
    private function modification(
        float $price,
        ?TaxRuleCollection $taxRules,
        int $position = 0,
        ?array $priceDefinition = null,
    ): OrderPriceModificationEntity {
        $modification = new OrderPriceModificationEntity();
        $modification->setId(Uuid::randomHex());
        $modification->setOrderId(Uuid::randomHex());
        $modification->setOrderVersionId(Uuid::randomHex());
        $modification->setLabel('Test modification');
        $modification->setPrice($price);
        $modification->setPosition($position);
        $modification->setTaxRules($taxRules);
        $modification->setPriceDefinition($priceDefinition);

        return $modification;
    }

    /**
     * @return array<string, mixed>
     */
    private function percentageDefinition(float $percentage, bool $lineItems, bool $shipping): array
    {
        return [
            'type' => 'percentage',
            'percentage' => $percentage,
            'target' => ['lineItems' => $lineItems, 'shipping' => $shipping],
            'filter' => null,
        ];
    }

    private function priceAt(float $taxRate, float $totalPrice): PriceCollection
    {
        $definition = new QuantityPriceDefinition($totalPrice, new TaxRuleCollection([new TaxRule($taxRate)]));

        return new PriceCollection([$this->quantityPriceCalculator->calculate($definition, $this->context)]);
    }
}
