<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\PriceFactoryTrait;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

class CartPriceFacade
{
    use PriceFactoryTrait;

    private CartPrice $price;

    /**
     * @internal
     */
    public function __construct(CartPrice $price, CartFacadeHelper $services)
    {
        $this->price = $price;
        $this->services = $services;
    }

    public function net(): float
    {
        return $this->price->getNetPrice();
    }

    public function total(): float
    {
        return $this->price->getTotalPrice();
    }

    public function position(): float
    {
        return $this->price->getPositionPrice();
    }

    public function rounded(): float
    {
        return $this->total();
    }

    public function raw(): float
    {
        return $this->price->getRawTotal();
    }
}
