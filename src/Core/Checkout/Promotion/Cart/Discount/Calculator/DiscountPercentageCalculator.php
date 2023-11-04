<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DiscountPercentageCalculator
{
    public function __construct(
        private readonly AbsolutePriceCalculator $absolutePriceCalculator,
        private readonly PercentagePriceCalculator $percentagePriceCalculator
    ) {
    }

    /**
     * @throws InvalidPriceDefinitionException
     */
    public function calculate(
        DiscountLineItem $discount,
        DiscountPackageCollection $packages,
        SalesChannelContext $context
    ): DiscountCalculatorResult {
        $definition = $discount->getPriceDefinition();

        if (!$definition instanceof PercentagePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount->getLabel(), $discount->getCode());
        }

        $definedPercentage = -abs($definition->getPercentage());

        // now simply calculate the price object
        // with that sum for the corresponding line items.
        // we dont need to check on the actual item count in there,
        // because our calculation does always go for the original cart items
        // without considering any previously applied discounts.
        $affectedPrices = $packages->getAffectedPrices();

        $calculatedPrice = $this->percentagePriceCalculator->calculate(
            $definedPercentage,
            $affectedPrices,
            $context
        );

        // if our percentage discount has a maximum
        // threshold, then make sure to reduce the calculated
        // discount price to that maximum value.
        if ($this->hasMaxValue($discount)) {
            $maxValue = (float) $discount->getPayloadValue('maxValue');
            $actualDiscountPrice = $calculatedPrice->getTotalPrice();

            // check if our actual discount is higher than the maximum one
            if (abs($actualDiscountPrice) > abs($maxValue)) {
                $calculatedPrice = $this->absolutePriceCalculator->calculate(
                    -abs($maxValue),
                    $affectedPrices,
                    $context
                );

                // now get the assessment basis of all line items
                // including their quantities that need to be discounted
                // based on our discount definition.
                // the basis might only be from a few items and quantities of the cart
                $assessmentBasis = $affectedPrices->sum()->getTotalPrice();

                // we have to get our new fictional and lower percentage.
                // we now calculate the percentage with MAX VALUE against our basis
                // to get the percentage to reach only the max value.
                $definedPercentage = ($maxValue / $assessmentBasis) * 100;
            }
        }

        $composition = $this->getCompositionItems($definedPercentage, $packages);

        return new DiscountCalculatorResult($calculatedPrice, $composition);
    }

    /**
     * @return DiscountCompositionItem[]
     */
    private function getCompositionItems(float $percentage, DiscountPackageCollection $packages): array
    {
        $items = [];

        foreach ($packages as $package) {
            foreach ($package->getCartItems() as $lineItem) {
                if ($lineItem->getPrice() === null) {
                    continue;
                }

                $itemTotal = $lineItem->getQuantity() * $lineItem->getPrice()->getUnitPrice();
                $percentageFactor = abs($percentage) / 100.0;

                $items[] = new DiscountCompositionItem(
                    $lineItem->getId(),
                    $lineItem->getQuantity(),
                    $itemTotal * $percentageFactor
                );
            }
        }

        return $items;
    }

    private function hasMaxValue(DiscountLineItem $discount): bool
    {
        if (!$discount->hasPayloadValue('maxValue')) {
            return false;
        }

        if (\is_array($discount->getPayloadValue('maxValue'))) {
            return false;
        }

        // if we have an empty string value then we convert it to 0.00 when casting it,  thus we create an early return
        return trim((string) $discount->getPayloadValue('maxValue')) !== '';
    }
}
