<?php declare(strict_types=1);

namespace Shopware\Shipping\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Shipping\Struct\ShippingMethodBasicStruct;

class ShippingMethodBasicCollection extends EntityCollection
{
    /**
     * @var ShippingMethodBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ShippingMethodBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ShippingMethodBasicStruct
    {
        return parent::current();
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (ShippingMethodBasicStruct $shippingMethod) {
            return $shippingMethod->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): ShippingMethodBasicCollection
    {
        return $this->filter(function (ShippingMethodBasicStruct $shippingMethod) use ($uuid) {
            return $shippingMethod->getCustomerGroupUuid() === $uuid;
        });
    }

    public function getPriceUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPrices()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getPrices(): ShippingMethodPriceBasicCollection
    {
        $collection = new ShippingMethodPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPrices()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodBasicStruct::class;
    }
}
