<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DiscountFixedUnitPriceCalculator
{
    private AbsolutePriceCalculator $absolutePriceCalculator;

    public function __construct(AbsolutePriceCalculator $absolutePriceCalculator)
    {
        $this->absolutePriceCalculator = $absolutePriceCalculator;
    }

    /**
     * @throws InvalidPriceDefinitionException
     */
    public function calculate(
        DiscountLineItem $discount,
        DiscountPackageCollection $packages,
        SalesChannelContext $context
    ): DiscountCalculatorResult {
        /** @var AbsolutePriceDefinition|null $priceDefinition */
        $priceDefinition = $discount->getPriceDefinition();

        if (!$priceDefinition instanceof AbsolutePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount->getLabel(), $discount->getCode());
        }

        $fixedUnitPrice = abs($priceDefinition->getPrice());

        $totalDiscountSum = 0.0;

        $composition = [];

        foreach ($packages as $package) {
            foreach ($package->getCartItems() as $lineItem) {
                if ($lineItem->getPrice() === null) {
                    continue;
                }

                $quantity = $lineItem->getQuantity();
                $itemUnitPrice = $lineItem->getPrice()->getUnitPrice();

                if ($itemUnitPrice > $fixedUnitPrice) {
                    // check if discount exceeds or not, beware of quantity
                    $discountDiffPrice = ($itemUnitPrice - $fixedUnitPrice) * $quantity;
                    // add to our total discount sum
                    $totalDiscountSum += $discountDiffPrice;

                    // add a reference, so we know what items are discounted
                    $composition[] = new DiscountCompositionItem($lineItem->getId(), $quantity, $discountDiffPrice);
                }
            }
        }

        // now calculate the correct price
        // from our collected total discount price
        $discountPrice = $this->absolutePriceCalculator->calculate(
            -abs($totalDiscountSum),
            $packages->getAffectedPrices(),
            $context
        );

        return new DiscountCalculatorResult($discountPrice, $composition);
    }
}
