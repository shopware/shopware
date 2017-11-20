<?php declare(strict_types=1);

namespace Shopware\Shipping\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Shipping\Struct\ShippingMethodPriceBasicStruct;

class ShippingMethodPriceBasicCollection extends EntityCollection
{
    /**
     * @var ShippingMethodPriceBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ShippingMethodPriceBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ShippingMethodPriceBasicStruct
    {
        return parent::current();
    }

    public function getShippingMethodUuids(): array
    {
        return $this->fmap(function (ShippingMethodPriceBasicStruct $shippingMethodPrice) {
            return $shippingMethodPrice->getShippingMethodUuid();
        });
    }

    public function filterByShippingMethodUuid(string $uuid): ShippingMethodPriceBasicCollection
    {
        return $this->filter(function (ShippingMethodPriceBasicStruct $shippingMethodPrice) use ($uuid) {
            return $shippingMethodPrice->getShippingMethodUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodPriceBasicStruct::class;
    }
}
