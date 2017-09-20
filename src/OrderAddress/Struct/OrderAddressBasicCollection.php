<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\Framework\Struct\Collection;

class OrderAddressBasicCollection extends Collection
{
    /**
     * @var OrderAddressBasicStruct[]
     */
    protected $elements = [];

    public function add(OrderAddressBasicStruct $orderAddress): void
    {
        $key = $this->getKey($orderAddress);
        $this->elements[$key] = $orderAddress;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(OrderAddressBasicStruct $orderAddress): void
    {
        parent::doRemoveByKey($this->getKey($orderAddress));
    }

    public function exists(OrderAddressBasicStruct $orderAddress): bool
    {
        return parent::has($this->getKey($orderAddress));
    }

    public function getList(array $uuids): OrderAddressBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? OrderAddressBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
            return $orderAddress->getUuid();
        });
    }

    public function getAreaCountryUuids(): array
    {
        return $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
            return $orderAddress->getAreaCountryUuid();
        });
    }

    public function filterByAreaCountryUuid(string $uuid): OrderAddressBasicCollection
    {
        return $this->filter(function (OrderAddressBasicStruct $orderAddress) use ($uuid) {
            return $orderAddress->getAreaCountryUuid() === $uuid;
        });
    }

    public function getAreaCountryStateUuids(): array
    {
        return $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
            return $orderAddress->getAreaCountryStateUuid();
        });
    }

    public function filterByAreaCountryStateUuid(string $uuid): OrderAddressBasicCollection
    {
        return $this->filter(function (OrderAddressBasicStruct $orderAddress) use ($uuid) {
            return $orderAddress->getAreaCountryStateUuid() === $uuid;
        });
    }

    public function getCountries(): AreaCountryBasicCollection
    {
        return new AreaCountryBasicCollection(
            $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
                return $orderAddress->getCountry();
            })
        );
    }

    public function getStates(): AreaCountryStateBasicCollection
    {
        return new AreaCountryStateBasicCollection(
            $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
                return $orderAddress->getState();
            })
        );
    }

    protected function getKey(OrderAddressBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
