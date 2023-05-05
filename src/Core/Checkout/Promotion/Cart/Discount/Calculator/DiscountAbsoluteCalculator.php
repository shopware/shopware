<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
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
            throw new InvalidPriceDefinitionException($discount->getLabel(), $discount->getCode());
        }

        $discountValue = -abs($definition->getPrice());

        $affectedPrices = $packages->getAffectedPrices();

        $price = $this->priceCalculator->calculate(
            $discountValue,
            $affectedPrices,
            $context
        );

        $composition = $this->getCompositionItems(
            $discountValue,
            $packages,
            $affectedPrices
        );

        return new DiscountCalculatorResult($price, $composition);
    }

    /**
     * @return DiscountCompositionItem[]
     */
    private function getCompositionItems(float $discountValue, DiscountPackageCollection $packages, PriceCollection $affectedPrices): array
    {
        $totalOriginalSum = $affectedPrices->sum()->getTotalPrice();

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
