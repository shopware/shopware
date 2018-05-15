<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Checkout\Shipping\Struct\ShippingMethodPriceBasicStruct;

class ShippingMethodPriceBasicCollection extends EntityCollection
{
    /**
     * @var ShippingMethodPriceBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ShippingMethodPriceBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ShippingMethodPriceBasicStruct
    {
        return parent::current();
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (ShippingMethodPriceBasicStruct $shippingMethodPrice) {
            return $shippingMethodPrice->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (ShippingMethodPriceBasicStruct $shippingMethodPrice) use ($id) {
            return $shippingMethodPrice->getShippingMethodId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodPriceBasicStruct::class;
    }
}
