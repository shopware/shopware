<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DiscountFixedPriceCalculator
{
    public function __construct(private readonly AbsolutePriceCalculator $absolutePriceCalculator)
    {
    }

    /**
     * @throws InvalidPriceDefinitionException
     * @throws CartException
     */
    public function calculate(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountCalculatorResult
    {
        /** @var AbsolutePriceDefinition|null $priceDefinition */
        $priceDefinition = $discount->getPriceDefinition();

        if (!$priceDefinition instanceof AbsolutePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount->getLabel(), $discount->getCode());
        }

        $fixedTotalPrice = abs($priceDefinition->getPrice());

        $discountDiff = $this->getTotalDiscountDiffSum($fixedTotalPrice, $packages);

        // now calculate the correct price
        // from our collected total discount price
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

        return $totalProductPrices - ($fixedPackagePrice * $packages->count());
    }

    /**
     * @return array<DiscountCompositionItem>
     */
    private function getCompositionItems(float $discountValue, DiscountPackageCollection $packages): array
    {
        $totalOriginalSum = $packages->getAffectedPrices()->sum()->getTotalPrice();

        $items = [];

        foreach ($packages as $package) {
            foreach ($package->getCartItems() as $lineItem) {
                if ($lineItem->getPrice() === null) {
                    continue;
                }

                $itemTotal = $lineItem->getPrice()->getTotalPrice();

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
