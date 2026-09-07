<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationEntity;
use Shopware\Core\Checkout\PriceModifier\PriceModifier;
use Shopware\Core\Checkout\PriceModifier\PriceModifierAbsolutePriceDefinition;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCalculatedPrice;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifierPriceDefinitionFactory;
use Shopware\Core\Checkout\PriceModifier\PriceModifierResult;
use Shopware\Core\Checkout\PriceModifier\PriceProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Reapplies whatever order_price_modification rows OrderPriceModificationCollector just loaded for
 * the order this cart originated from -- a no-op when no order exists yet. This is the only thing
 * that reapplies modifications once an order exists, so admin edits survive every recalculation.
 *
 * An "absolute" (or manually added) row reapplies its persisted `price`. A "percentage" row is
 * recomputed from its frozen price_definition against the current prices of its `target` -- its
 * persisted `price` is only a cache of the value at placement (see percentageAmount()).
 *
 * Every taxable row (by position) is applied first, then every tax_exempt row against what remains.
 * A reduction is capped so the running total can't go below zero; a surcharge is uncapped. The cap
 * must be checked per target tax rate, not just in aggregate, since a restricted row's reduction is
 * split proportionally only across its own rate(s) -- see remainingPricesAtRates().
 */
#[Package('checkout')]
final class OrderPriceModificationProcessor implements PriceProcessorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbsolutePriceCalculator $absolutePriceCalculator,
        private readonly QuantityPriceCalculator $quantityPriceCalculator,
        private readonly PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        private readonly CashRounding $rounding
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
        /** @var OrderPriceModificationCollection|null $modifications */
        $modifications = $data->get(OrderPriceModificationCollector::DATA_KEY);

        if ($modifications === null || $modifications->count() === 0) {
            return new PriceModifierResult($prices, $shippingCosts);
        }

        $modifiers = new PriceModifierCollection();
        $eligible = $prices->getTotalPriceAmount() + $shippingCosts->getTotalPriceAmount();

        // Tracks only this processor's own delta; $additionalCosts keeps accumulating the full
        // running total (needed for eligibility math below), but the return value must not echo
        // back what an earlier processor in the chain already contributed.
        $ownAdditionalCosts = new PriceCollection();

        $taxable = $modifications->filter(fn (OrderPriceModificationEntity $modification) => !$modification->isTaxExempt());
        $taxable->sort(fn (OrderPriceModificationEntity $a, OrderPriceModificationEntity $b) => $a->getPosition() <=> $b->getPosition());

        foreach ($taxable as $modification) {
            // Non-null: $taxable already excludes isTaxExempt() (getTaxRules() === null) rows.
            /** @var TaxRuleCollection $taxRules */
            $taxRules = $modification->getTaxRules();

            // Only a percentage row targeting shipping draws against shipping; every other row's base
            // is the goods alone. Restricted means the listed rate(s), unrestricted every rate in it.
            $base = $this->baseFor($modification, $prices, $shippingCosts);
            $targetRates = $taxRules->count() > 0
                ? $taxRules
                : $this->percentageTaxRuleBuilder->buildCollectionRules($base->getCalculatedTaxes(), $base->getTotalPriceAmount());

            $pricesForModification = $this->remainingPricesAtRates($base, $additionalCosts, $targetRates, $context);

            // Cap against $pricesForModification's own total, not the larger $eligible -- that's the
            // actual base this row gets redistributed across below.
            $amount = $this->capIfReduction($this->amountFor($modification, $prices, $shippingCosts, $context), $pricesForModification->getTotalPriceAmount());

            // Epsilon-tolerant: this runs before downstream rounding, so a near-zero float shouldn't
            // be treated as nonzero.
            if (FloatComparator::equals($amount, 0.0)) {
                continue;
            }

            if ($taxRules->count() > 0 && $pricesForModification->count() === 0 && $amount > 0.0) {
                // Restricted rate(s) fully consumed by a prior row (or never present) -- tax the
                // surcharge at its rate explicitly rather than deriving an untaxed rule from nothing.
                $definition = new QuantityPriceDefinition($amount, $taxRules);
                $adjustment = $this->quantityPriceCalculator->calculate($definition, $context);
            } else {
                $adjustment = $this->absolutePriceCalculator->calculate($amount, $pricesForModification, $context);
            }
            $additionalCosts = $additionalCosts->merge(new PriceCollection([$adjustment]));
            $ownAdditionalCosts = $ownAdditionalCosts->merge(new PriceCollection([$adjustment]));
            $modifiers->add($this->toPriceModifier($modification, $amount, PriceModifierCalculatedPrice::fromCalculatedPrice($adjustment)));
        }

        $taxExempt = $modifications->filter(fn (OrderPriceModificationEntity $modification) => $modification->isTaxExempt());
        $taxExempt->sort(fn (OrderPriceModificationEntity $a, OrderPriceModificationEntity $b) => $a->getPosition() <=> $b->getPosition());

        // Same split as for $additionalCosts: the cap needs the running total including every earlier
        // processor's tax-exempt contribution, the return value only this processor's own.
        $ownTaxExemptAdjustment = 0.0;

        foreach ($taxExempt as $modification) {
            $amount = $this->capIfReduction($this->amountFor($modification, $prices, $shippingCosts, $context), $eligible + $additionalCosts->getTotalPriceAmount() + $taxExemptAdjustment + $ownTaxExemptAdjustment);
            // Never passes through AbsolutePriceCalculator/QuantityPriceCalculator, so round
            // explicitly -- and again after accumulating, so later rows don't cap against drift.
            $amount = $this->rounding->mathRound($amount, $context->getItemRounding());

            if ($amount === 0.0) {
                continue;
            }

            $ownTaxExemptAdjustment = $this->rounding->mathRound($ownTaxExemptAdjustment + $amount, $context->getItemRounding());
            $modifiers->add($this->toPriceModifier($modification, $amount, $this->taxExemptPrice($amount, $prices->merge($shippingCosts))));
        }

        return new PriceModifierResult($prices, $shippingCosts, $ownAdditionalCosts, $ownTaxExemptAdjustment, $modifiers);
    }

    /**
     * The row's signed amount before capping: its persisted `price`, or for a "percentage" row the
     * value recomputed from its price_definition.
     */
    private function amountFor(OrderPriceModificationEntity $modification, PriceCollection $prices, PriceCollection $shippingCosts, SalesChannelContext $context): float
    {
        $definition = $modification->getPriceDefinition();

        if (($definition['type'] ?? null) !== OrderPriceModificationDefinition::PRICE_DEFINITION_TYPE_PERCENTAGE) {
            return $modification->getPrice();
        }

        return $this->rounding->mathRound(
            $this->baseFor($modification, $prices, $shippingCosts)->getTotalPriceAmount() * (float) ($definition['percentage'] ?? 0.0) / 100,
            $context->getItemRounding()
        );
    }

    /**
     * The collection(s) a row is computed against: the goods for every row except a "percentage"
     * one, which uses exactly what its frozen `target` names -- against the original, unmodified
     * prices, so the same definition always yields the same share regardless of other rows.
     */
    private function baseFor(OrderPriceModificationEntity $modification, PriceCollection $prices, PriceCollection $shippingCosts): PriceCollection
    {
        $definition = $modification->getPriceDefinition();

        if (($definition['type'] ?? null) !== OrderPriceModificationDefinition::PRICE_DEFINITION_TYPE_PERCENTAGE) {
            return $prices;
        }

        $target = $definition['target'] ?? ['lineItems' => true, 'shipping' => true];
        $base = new PriceCollection();

        if ($target['lineItems'] ?? false) {
            $base = $base->merge($prices);
        }

        if ($target['shipping'] ?? false) {
            $base = $base->merge($shippingCosts);
        }

        return $base;
    }

    /**
     * A negative (reduction) amount must not drive the running total below zero -- cap it at whatever
     * is still eligible. A positive (surcharge) amount is returned unchanged; it has no ceiling.
     */
    private function capIfReduction(float $amount, float $runningEligible): float
    {
        if ($amount >= 0.0) {
            return $amount;
        }

        return max($amount, -max(0.0, $runningEligible));
    }

    /**
     * getCalculatedTaxes() is always empty -- a tax-exempt amount never touches Cart::price's own
     * calculatedTaxes. getTaxRules() is still populated (one entry per rate in $targetPrices) purely
     * to record which rates the modifier conceptually corresponds to.
     */
    private function taxExemptPrice(float $amount, PriceCollection $targetPrices): PriceModifierCalculatedPrice
    {
        $taxRules = $this->percentageTaxRuleBuilder->buildCollectionRules($targetPrices->getCalculatedTaxes(), $targetPrices->getTotalPriceAmount());

        return new PriceModifierCalculatedPrice($amount, new CalculatedTaxCollection(), $taxRules);
    }

    private function toPriceModifier(OrderPriceModificationEntity $modification, float $amount, PriceModifierCalculatedPrice $price): PriceModifier
    {
        // A manually-added row has no structured price_definition, so synthesize one from the
        // entity's own columns. Note NULL means "unrestricted" here but "tax-exempt" on the entity --
        // an empty persisted collection (taxable/unrestricted) must translate to NULL, not [].
        $persistedTaxRules = $modification->getTaxRules();
        $priceDefinition = PriceModifierPriceDefinitionFactory::fromArray($modification->getPriceDefinition())
            ?? new PriceModifierAbsolutePriceDefinition(
                $amount,
                $persistedTaxRules !== null && $persistedTaxRules->count() > 0 ? $persistedTaxRules : null,
                $modification->isTaxExempt()
            );

        return new PriceModifier(
            label: $modification->getLabel(),
            price: $price,
            priceDefinition: $priceDefinition,
            id: $modification->getId(),
            type: $modification->getType(),
            referencedId: $modification->getReferencedId(),
            description: $modification->getDescription(),
            payload: $modification->getPayload(),
        );
    }

    /**
     * One single-rate CalculatedPrice per $targetRates entry, holding what's still remaining in that
     * rate's pool after prior rows. Built rate-by-rate via amountAtTaxRates() rather than merging
     * $prices with $additionalCosts by membership, since one additionalCosts entry can itself span
     * multiple rates.
     */
    private function remainingPricesAtRates(PriceCollection $prices, PriceCollection $additionalCosts, TaxRuleCollection $targetRates, SalesChannelContext $context): PriceCollection
    {
        $remaining = new PriceCollection();

        foreach ($targetRates as $targetRate) {
            $rate = new TaxRuleCollection([new TaxRule($targetRate->getTaxRate())]);
            $amount = $this->amountAtTaxRates($prices, $rate) + $this->amountAtTaxRates($additionalCosts, $rate);

            // Unrounded sum of proportional shares -- a fully-consumed rate can land on e.g. 1e-13.
            if (FloatComparator::equals($amount, 0.0)) {
                continue;
            }

            $remaining->add($this->quantityPriceCalculator->calculate(new QuantityPriceDefinition($amount, $rate), $context));
        }

        return $remaining;
    }

    /**
     * How much of $prices' total is attributable to any of $taxRules' rates, reading each
     * CalculatedPrice's own percentage share per rate -- works for both single-rate line items and
     * multi-rate additionalCosts entries.
     */
    private function amountAtTaxRates(PriceCollection $prices, TaxRuleCollection $taxRules): float
    {
        $sum = 0.0;

        foreach ($prices as $price) {
            foreach ($taxRules as $taxRule) {
                $rule = $price->getTaxRules()->get((string) $taxRule->getTaxRate());

                if ($rule !== null) {
                    $sum += $price->getTotalPrice() * ($rule->getPercentage() / 100);
                }
            }
        }

        return $sum;
    }
}
