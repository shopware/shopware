<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Country\Collection\CountryBasicCollection;
use Shopware\Api\Country\Collection\CountryStateBasicCollection;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Order\Struct\OrderAddressBasicStruct;

class OrderAddressBasicCollection extends EntityCollection
{
    /**
     * @var OrderAddressBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? OrderAddressBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): OrderAddressBasicStruct
    {
        return parent::current();
    }

    public function getCountryUuids(): array
    {
        return $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
            return $orderAddress->getCountryUuid();
        });
    }

    public function filterByCountryUuid(string $uuid): OrderAddressBasicCollection
    {
        return $this->filter(function (OrderAddressBasicStruct $orderAddress) use ($uuid) {
            return $orderAddress->getCountryUuid() === $uuid;
        });
    }

    public function getCountryStateUuids(): array
    {
        return $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
            return $orderAddress->getCountryStateUuid();
        });
    }

    public function filterByCountryStateUuid(string $uuid): OrderAddressBasicCollection
    {
        return $this->filter(function (OrderAddressBasicStruct $orderAddress) use ($uuid) {
            return $orderAddress->getCountryStateUuid() === $uuid;
        });
    }

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
                return $orderAddress->getCountry();
            })
        );
    }

    public function getCountryStates(): CountryStateBasicCollection
    {
        return new CountryStateBasicCollection(
            $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
                return $orderAddress->getCountryState();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderAddressBasicStruct::class;
    }
}
