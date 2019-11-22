<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;

class DiscountCalculatorResult
{
    /**
     * @var CalculatedPrice
     */
    private $price;

    /**
     * @var DiscountCompositionItem[]
     */
    private $compositionItems;

    /**
     * @param DiscountCompositionItem[] $discountedItems
     */
    public function __construct(CalculatedPrice $price, array $discountedItems)
    {
        $this->price = $price;
        $this->compositionItems = $discountedItems;
    }

    public function getPrice(): CalculatedPrice
    {
        return $this->price;
    }

    /**
     * @return DiscountCompositionItem[]
     */
    public function getCompositionItems(): array
    {
        return $this->compositionItems;
    }
}
