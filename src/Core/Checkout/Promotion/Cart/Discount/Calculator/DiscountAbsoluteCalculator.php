<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('buyers-experience')]
class DiscountAbsoluteCalculator implements DiscountCalculatorInterface
{
    public function __construct(private readonly AbsolutePriceCalculator $priceCalculator)
    {
    }

    /**
     * @throws InvalidPriceDefinitionException
     */
    public function calculate(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountCalculatorResult
    {
        /** @var AbsolutePriceDefinition $definition */
        $definition = $discount->getPriceDefinition();

        if (!$definition instanceof AbsolutePriceDefinition) {
            throw PromotionException::invalidPriceDefinition($discount->getLabel(), $discount->getCode());
        }

        $affectedPrices = $packages->getAffectedPrices();

        $totalOriginalSum = $affectedPrices->sum()->getTotalPrice();
        $discountValue = -min(abs($definition->getPrice()), $totalOriginalSum);

        $price = $this->priceCalculator->calculate(
            $discountValue,
            $affectedPrices,
            $context
        );

        $composition = $this->getCompositionItems(
            $discountValue,
            $packages,
            $totalOriginalSum
        );

        return new DiscountCalculatorResult($price, $composition);
    }

    /**
     * @return DiscountCompositionItem[]
     */
    private function getCompositionItems(float $discountValue, DiscountPackageCollection $packages, float $totalOriginalSum): array
    {
        $items = [];

        foreach ($packages as $package) {
            foreach ($package->getCartItems() as $lineItem) {
                if ($lineItem->getPrice() === null) {
                    continue;
                }

                $itemTotal = $lineItem->getPrice()->getTotalPrice();

                $factor = $totalOriginalSum === 0.0 ? 0 : $itemTotal / $totalOriginalSum;

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
