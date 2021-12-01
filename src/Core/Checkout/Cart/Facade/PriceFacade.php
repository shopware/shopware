<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\PriceFactoryTrait;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;

class PriceFacade
{
    use PriceFactoryTrait;

    private CalculatedPrice $price;

    /**
     * @internal
     */
    public function __construct(CalculatedPrice $price, CartFacadeHelper $services)
    {
        $this->price = $price;
        $this->services = $services;
    }

    public function getTotal(): float
    {
        return $this->price->getTotalPrice();
    }

    public function getUnit(): float
    {
        return $this->price->getUnitPrice();
    }

    public function getQuantity(): int
    {
        return $this->price->getQuantity();
    }
}
