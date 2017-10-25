<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Struct;

use Shopware\Framework\Struct\Collection;

class ShippingMethodBasicCollection extends Collection
{
    /**
     * @var ShippingMethodBasicStruct[]
     */
    protected $elements = [];

    public function add(ShippingMethodBasicStruct $shippingMethod): void
    {
        $key = $this->getKey($shippingMethod);
        $this->elements[$key] = $shippingMethod;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ShippingMethodBasicStruct $shippingMethod): void
    {
        parent::doRemoveByKey($this->getKey($shippingMethod));
    }

    public function exists(ShippingMethodBasicStruct $shippingMethod): bool
    {
        return parent::has($this->getKey($shippingMethod));
    }

    public function getList(array $uuids): ShippingMethodBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ShippingMethodBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ShippingMethodBasicStruct $shippingMethod) {
            return $shippingMethod->getUuid();
        });
    }

    public function merge(ShippingMethodBasicCollection $collection)
    {
        /** @var ShippingMethodBasicStruct $shippingMethod */
        foreach ($collection as $shippingMethod) {
            if ($this->has($this->getKey($shippingMethod))) {
                continue;
            }
            $this->add($shippingMethod);
        }
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (ShippingMethodBasicStruct $shippingMethod) {
            return $shippingMethod->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): ShippingMethodBasicCollection
    {
        return $this->filter(function (ShippingMethodBasicStruct $shippingMethod) use ($uuid) {
            return $shippingMethod->getShopUuid() === $uuid;
        });
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

    public function current(): ShippingMethodBasicStruct
    {
        return parent::current();
    }

    protected function getKey(ShippingMethodBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
