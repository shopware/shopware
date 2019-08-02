<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;

class DiscountCalculatorResult
{
    /**
     * @var CalculatedPrice
     */
    private $price;

    /**
     * @var array
     */
    private $compositionItems;

    public function __construct(CalculatedPrice $price, array $discountedItems)
    {
        $this->price = $price;
        $this->compositionItems = $discountedItems;
    }

    public function getPrice(): CalculatedPrice
    {
        return $this->price;
    }

    public function getCompositionItems(): array
    {
        return $this->compositionItems;
    }
}
