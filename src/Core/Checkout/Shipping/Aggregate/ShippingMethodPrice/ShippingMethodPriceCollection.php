<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice;


use Shopware\Core\Framework\ORM\EntityCollection;

class ShippingMethodPriceCollection extends EntityCollection
{
    /**
     * @var ShippingMethodPriceStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ShippingMethodPriceStruct
    {
        return parent::get($id);
    }

    public function current(): ShippingMethodPriceStruct
    {
        return parent::current();
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (ShippingMethodPriceStruct $shippingMethodPrice) {
            return $shippingMethodPrice->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (ShippingMethodPriceStruct $shippingMethodPrice) use ($id) {
            return $shippingMethodPrice->getShippingMethodId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodPriceStruct::class;
    }
}
