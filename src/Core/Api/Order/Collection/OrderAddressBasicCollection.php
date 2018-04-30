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

    public function get(string $id): ? OrderAddressBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderAddressBasicStruct
    {
        return parent::current();
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
            return $orderAddress->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (OrderAddressBasicStruct $orderAddress) use ($id) {
            return $orderAddress->getCountryId() === $id;
        });
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
            return $orderAddress->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (OrderAddressBasicStruct $orderAddress) use ($id) {
            return $orderAddress->getCountryStateId() === $id;
        });
    }

    public function getVatIds(): array
    {
        return $this->fmap(function (OrderAddressBasicStruct $orderAddress) {
            return $orderAddress->getVatId();
        });
    }

    public function filterByVatId(string $id): self
    {
        return $this->filter(function (OrderAddressBasicStruct $orderAddress) use ($id) {
            return $orderAddress->getVatId() === $id;
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
