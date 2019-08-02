<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
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
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException
     */
    public function calculate(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountCalculatorResult
    {
        /** @var AbsolutePriceDefinition|null $priceDefinition */
        $priceDefinition = $discount->getPriceDefinition();

        if (!$priceDefinition instanceof AbsolutePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount->getLabel(), $discount->getCode());
        }

        /** @var float $fixedTotalPrice */
        $fixedTotalPrice = (float) abs($priceDefinition->getPrice());

        /** @var float $discountDiff */
        $discountDiff = $this->getTotalDiscountDiffSum($fixedTotalPrice, $packages);

        // now calculate the correct price
        // from our collected total discount price
        /** @var CalculatedPrice $discountPrice */
        $discountPrice = $this->absolutePriceCalculator->calculate(
            -abs($discountDiff),
            $packages->getAffectedPrices(),
            $context
        );

        $composition = $this->getCompositionItems(
            $discountPrice->getTotalPrice(),
            $packages
        );

        return new DiscountCalculatorResult($discountPrice, $composition);
    }

    private function getTotalDiscountDiffSum(float $fixedPackagePrice, DiscountPackageCollection $packages): float
    {
        $totalProductPrices = $packages->getAffectedPrices()->sum()->getTotalPrice();

        /** @var float $discountDiff */
        $discountDiff = $totalProductPrices - ($fixedPackagePrice * $packages->count());

        return $discountDiff;
    }

    private function getCompositionItems(float $discountValue, DiscountPackageCollection $packages): array
    {
        /** @var float $totalOriginalSum */
        $totalOriginalSum = $packages->getAffectedPrices()->sum()->getTotalPrice();

        $items = [];

        /** @var DiscountPackage $package */
        foreach ($packages as $package) {
            /** @var LineItem $lineItem */
            foreach ($package->getCartItems() as $lineItem) {
                /** @var float $itemTotal */
                $itemTotal = $lineItem->getPrice()->getTotalPrice();

                /** @var float $factor */
                $factor = $itemTotal / $totalOriginalSum;

                $items[] = new DiscountCompositionItem(
                    $lineItem->getId(),
                    $lineItem->getQuantity(),
                    abs($discountValue) * $factor
                );
            }
        }

        return $items;
    }
}
