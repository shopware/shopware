<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DiscountPercentageCalculator
{
    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    public function __construct(AbsolutePriceCalculator $absolutePriceCalculator)
    {
        $this->absolutePriceCalculator = $absolutePriceCalculator;
    }

    /**
     * @throws InvalidPriceDefinitionException
     */
    public function calculate(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountCalculatorResult
    {
        /** @var PercentagePriceDefinition $definition */
        $definition = $discount->getPriceDefinition();

        if (!$definition instanceof PercentagePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount->getLabel(), $discount->getCode());
        }

        /** @var float $definedPercentage */
        $definedPercentage = -abs($definition->getPercentage());

        // now get the assessment basis of all line items
        // including their quantities that need to be discounted
        // based on our discount definition.
        // the basis might only be from a few items and quantities of the cart
        /** @var float $assessmentBasis */
        $assessmentBasis = $packages->getAffectedPrices()->sum()->getTotalPrice();

        // calculate our price from that sum
        /** @var float $discountPrice */
        $discountPrice = ($definedPercentage / 100.0) * $assessmentBasis;

        // now simply calculate the price object
        // with that sum for the corresponding line items.
        // we dont need to check on the actual item count in there,
        // because our calculation does always go for the original cart items
        // without considering any previously applied discounts.
        /** @var CalculatedPrice $calculatedPrice */
        $calculatedPrice = $this->absolutePriceCalculator->calculate(
            -abs($discountPrice),
            $packages->getAffectedPrices(),
            $context
        );

        // if our percentage discount has a maximum
        // threshold, then make sure to reduce the calculated
        // discount price to that maximum value.
        if ($this->hasMaxValue($discount)) {
            /** @var float $maxValue */
            $maxValue = $this->getMaxValue($discount);
            /** @var float $actualDiscountPrice */
            $actualDiscountPrice = $calculatedPrice->getTotalPrice();

            // check if our actual discount is higher than the maximum one
            if (abs($actualDiscountPrice) > abs($maxValue)) {
                /** @var CalculatedPrice $calculatedPrice */
                $calculatedPrice = $this->absolutePriceCalculator->calculate(
                    -abs($maxValue),
                    $packages->getAffectedPrices(),
                    $context
                );

                // we have to get our new fictional and lower percentage.
                // we now calculate the percentage with MAX VALUE against our basis
                // to get the percentage to reach only the max value.
                $reducedPercentage = ($maxValue / $assessmentBasis) * 100;
                $definedPercentage = $reducedPercentage;
            }
        }

        $composition = $this->getCompositionItems($definedPercentage, $packages);

        return new DiscountCalculatorResult($calculatedPrice, $composition);
    }

    private function getCompositionItems(float $percentage, DiscountPackageCollection $packages): array
    {
        $items = [];

        /** @var DiscountPackage $package */
        foreach ($packages as $package) {
            /** @var LineItem $lineItem */
            foreach ($package->getCartItems() as $lineItem) {
                /** @var float $itemTotal */
                $itemTotal = $lineItem->getQuantity() * $lineItem->getPrice()->getUnitPrice();
                /** @var float $percentageFactor */
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
        if (!array_key_exists('maxValue', $discount->getPayload())) {
            return false;
        }

        /** @var string $stringValue */
        $stringValue = $discount->getPayload()['maxValue'];

        // if we have an empty string value
        // then we convert it to 0.00 when casting it,
        // thus we create an early return
        return trim($stringValue) !== '';
    }

    private function getMaxValue(DiscountLineItem $discount): float
    {
        /** @var string $stringValue */
        $stringValue = $discount->getPayload()['maxValue'];

        /** @var float $maxValue */
        $maxValue = (float) $stringValue;

        return $maxValue;
    }
}
