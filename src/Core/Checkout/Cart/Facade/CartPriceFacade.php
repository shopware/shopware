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
    public function __construct(CartPrice $price, CartFacadeHelper $helper)
    {
        $this->price = $price;
        $this->helper = $helper;
    }

    public function getNet(): float
    {
        return $this->price->getNetPrice();
    }

    public function getTotal(): float
    {
        return $this->price->getTotalPrice();
    }

    public function getPosition(): float
    {
        return $this->price->getPositionPrice();
    }

    public function getRounded(): float
    {
        return $this->getTotal();
    }

    public function getRaw(): float
    {
        return $this->price->getRawTotal();
    }
}
