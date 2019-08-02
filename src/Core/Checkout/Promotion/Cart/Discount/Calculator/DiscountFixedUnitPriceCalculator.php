<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

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

class DiscountFixedUnitPriceCalculator
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

        $fixedUnitPrice = (float) abs($priceDefinition->getPrice());

        /** @var float $totalDiscountSum */
        $totalDiscountSum = 0.0;

        /** @var DiscountCompositionItem[] $refMetaItems */
        $refMetaItems = [];

        /** @var LineItem $lineItem */
        foreach ($targetItems->getFlat() as $lineItem) {
            if (!$discount->hasItem($lineItem->getId())) {
                continue;
            }

            /** @var int $quantity */
            $quantity = $discount->getItem($lineItem->getId())->getQuantity();

            // if no quantity exists, skip
            if ($quantity <= 0) {
                continue;
            }

            /** @var float $itemUnitPrice */
            $itemUnitPrice = $lineItem->getPrice()->getUnitPrice();

            if ($itemUnitPrice > $fixedUnitPrice) {
                // check if discount exceeds or not, beware of quantity
                /** @var float $discountDiffPrice */
                $discountDiffPrice = ($itemUnitPrice - $fixedUnitPrice) * $quantity;
                // add to our total discount sum
                $totalDiscountSum += $discountDiffPrice;

                // add a reference, so we know what items are discounted
                $refMetaItems[] = new DiscountCompositionItem($lineItem->getId(), $quantity, $discountDiffPrice);
            }
        }

        // now calculate the correct price
        // from our collected total discount price
        /** @var CalculatedPrice $discountPrice */
        $discountPrice = $this->absolutePriceCalculator->calculate(
            -abs($totalDiscountSum),
            $targetPrices,
            $context
        );

        return new DiscountCalculatorResult(
            $discountPrice,
            $refMetaItems
        );
    }
}
