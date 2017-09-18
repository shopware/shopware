<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Struct;

use Shopware\Framework\Struct\Collection;

class ShippingMethodPriceBasicCollection extends Collection
{
    /**
     * @var ShippingMethodPriceBasicStruct[]
     */
    protected $elements = [];

    public function add(ShippingMethodPriceBasicStruct $shippingMethodPrice): void
    {
        $key = $this->getKey($shippingMethodPrice);
        $this->elements[$key] = $shippingMethodPrice;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ShippingMethodPriceBasicStruct $shippingMethodPrice): void
    {
        parent::doRemoveByKey($this->getKey($shippingMethodPrice));
    }

    public function exists(ShippingMethodPriceBasicStruct $shippingMethodPrice): bool
    {
        return parent::has($this->getKey($shippingMethodPrice));
    }

    public function getList(array $uuids): ShippingMethodPriceBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ShippingMethodPriceBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ShippingMethodPriceBasicStruct $shippingMethodPrice) {
            return $shippingMethodPrice->getUuid();
        });
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

    protected function getKey(ShippingMethodPriceBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
