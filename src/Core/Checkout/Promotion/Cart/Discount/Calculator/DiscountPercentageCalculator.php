<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
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
    public function calculate(DiscountCalculatorDefinition $discount, PriceCollection $targetPrices, LineItemCollection $targetItems, SalesChannelContext $context): DiscountCalculatorResult
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
        $assessmentBasis = $this->getAssessmentBasis($discount, $targetItems);

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
            $targetPrices,
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
                    $targetPrices,
                    $context
                );

                // we have to get our new fictional and lower percentage.
                // we now calculate the percentage with MAX VALUE against our basis
                // to get the percentage to reach only the max value.
                $reducedPercentage = ($maxValue / $assessmentBasis) * 100;
                $definedPercentage = $reducedPercentage;
            }
        }

        return new DiscountCalculatorResult(
            $calculatedPrice,
            $this->getCompositionItems($definedPercentage, $discount, $targetItems)
        );
    }

    private function getCompositionItems(float $percentage, DiscountCalculatorDefinition $discount, LineItemCollection $cartItems): array
    {
        $items = [];

        /** @var LineItem $lineItem */
        foreach ($cartItems->getFlat() as $lineItem) {
            $id = $lineItem->getId();

            if ($discount->hasItem($id)) {
                /** @var LineItemQuantity $discountData */
                $discountData = $discount->getItem($id);
                /** @var float $itemTotal */
                $itemTotal = $discountData->getQuantity() * $lineItem->getPrice()->getUnitPrice();
                /** @var float $percentageFactor */
                $percentageFactor = abs($percentage) / 100.0;

                $items[] = new DiscountCompositionItem(
                    $discountData->getLineItemId(),
                    $discountData->getQuantity(),
                    $itemTotal * $percentageFactor
                );
            }
        }

        return $items;
    }

    private function hasMaxValue(DiscountCalculatorDefinition $discount): bool
    {
        if (!array_key_exists('maxValue', $discount->getPayload())) {
            return false;
        }

        /** @var string $stringValue */
        $stringValue = $discount->getPayload()['maxValue'];

        // if we have an empty string value
        // then we convert it to 0.00 when casting it,
        // thus we create an early return
        if (trim($stringValue) === '') {
            return false;
        }

        return true;
    }

    private function getMaxValue(DiscountCalculatorDefinition $discount): float
    {
        /** @var string $stringValue */
        $stringValue = $discount->getPayload()['maxValue'];

        /** @var float $maxValue */
        $maxValue = (float) $stringValue;

        return $maxValue;
    }

    private function getAssessmentBasis(DiscountCalculatorDefinition $discount, LineItemCollection $items): float
    {
        $price = 0;

        /** @var LineItem $lineItem */
        foreach ($items->getFlat() as $lineItem) {
            $id = $lineItem->getId();

            if ($discount->hasItem($id)) {
                /** @var LineItemQuantity $discountData */
                $discountData = $discount->getItem($id);
                // now calculate a new unit sum based on the defined quantity
                $price += ($discountData->getQuantity() * $lineItem->getPrice()->getUnitPrice());
            }
        }

        return $price;
    }
}
