<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\PriceFactoryTrait;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Log\Package;

/**
 * @package checkout
 */
/**
 * The PriceFacade is a wrapper around a price.
 *
 * @script-service cart_manipulation
 */
#[Package('checkout')]
class PriceFacade
{
    use PriceFactoryTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly CalculatedPrice $price,
        CartFacadeHelper $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * `getTotal()` returns the total price for the line-item.
     *
     * @return float The total price as float.
     */
    public function getTotal(): float
    {
        return $this->price->getTotalPrice();
    }

    /**
     * `getUnit()` returns the unit price for the line-item.
     * This is equivalent to the total price of the line-item with the quantity 1.
     *
     * @return float The price per unit as float.
     */
    public function getUnit(): float
    {
        return $this->price->getUnitPrice();
    }

    /**
     * `getQuantity()` returns the quantity that was used to calculate the total price.
     *
     * @return int Returns the quantity.
     */
    public function getQuantity(): int
    {
        return $this->price->getQuantity();
    }
}
