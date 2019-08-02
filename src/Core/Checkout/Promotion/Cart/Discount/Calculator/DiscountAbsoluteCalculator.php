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
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DiscountAbsoluteCalculator implements DiscountCalculatorInterface
{
    /**
     * @var AbsolutePriceCalculator
     */
    private $priceCalculator;

    public function __construct(AbsolutePriceCalculator $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }

    /**
     * @throws InvalidPriceDefinitionException
     */
    public function calculate(DiscountCalculatorDefinition $discount, PriceCollection $targetPrices, LineItemCollection $targetItems, SalesChannelContext $context): DiscountCalculatorResult
    {
        /** @var AbsolutePriceDefinition $definition */
        $definition = $discount->getPriceDefinition();

        if (!$definition instanceof AbsolutePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount->getLabel(), $discount->getCode());
        }

        /** @var float $discountValue */
        $discountValue = -abs($definition->getPrice());

        /** @var CalculatedPrice $price */
        $price = $this->priceCalculator->calculate(
            $discountValue,
            $targetPrices,
            $context
        );

        return new DiscountCalculatorResult(
            $price,
            $this->getCompositionItems(
                $discountValue,
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
            $itemTotal = $item->getPrice()->getTotalPrice();

            /** @var float $factor */
            $factor = $itemTotal / $totalOriginalSum;

            $items[] = new DiscountCompositionItem(
                $item->getId(),
                $item->getQuantity(),
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
