<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DiscountFixedPriceCalculator
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
        /** @var AbsolutePriceDefinition|null $priceDefinition */
        $priceDefinition = $discount->getPriceDefinition();

        if (!$priceDefinition instanceof AbsolutePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount->getLabel(), $discount->getCode());
        }

        /** @var float $fixedTotalPrice */
        $fixedTotalPrice = (float) abs($priceDefinition->getPrice());

        $totalProductPrices = 0.0;

        /** @var LineItem $lineItem */
        foreach ($targetItems->getFlat() as $lineItem) {
            if ($discount->hasItem($lineItem->getId())) {
                /** @var int $quantity */
                $quantity = $discount->getItem($lineItem->getId())->getQuantity();
                /** @var float $itemUnitPrice */
                $itemUnitPrice = $lineItem->getPrice()->getUnitPrice();

                /* @var float $totalProductPrices */
                $totalProductPrices += $itemUnitPrice * $quantity;
            }
        }

        /** @var float $discountDiff */
        $discountDiff = $totalProductPrices - $fixedTotalPrice;

        // now calculate the correct price
        // from our collected total discount price
        /** @var CalculatedPrice $discountPrice */
        $discountPrice = $this->absolutePriceCalculator->calculate(
            -abs($discountDiff),
            $targetPrices,
            $context
        );

        return new DiscountCalculatorResult(
            $discountPrice,
            $this->getCompositionItems(
                $discountPrice->getTotalPrice(),
                $discount,
                $targetItems
            )
        );
    }

    private function getCompositionItems(float $discountValue, DiscountCalculatorDefinition $discount, LineItemCollection $cartItems): array
    {
        /** @var float $totalOriginalSum */
        $totalOriginalSum = $this->getAssessmentBasis($discount, $cartItems);

        $items = [];

        /** @var LineItem $item */
        foreach ($cartItems->getFlat() as $item) {
            if (!$discount->hasItem($item->getId())) {
                continue;
            }

            /** @var float $itemTotal */
            $itemTotal = $discount->getItem($item->getId())->getQuantity() * $item->getPrice()->getUnitPrice();

            /** @var float $factor */
            $factor = $itemTotal / $totalOriginalSum;

            $items[] = new DiscountCompositionItem(
                $item->getId(),
                $discount->getItem($item->getId())->getQuantity(),
                abs($discountValue) * $factor
            );
        }

        return $items;
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
