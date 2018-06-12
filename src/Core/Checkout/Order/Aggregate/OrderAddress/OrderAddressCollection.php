<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress;


use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\CountryCollection;

class OrderAddressCollection extends EntityCollection
{
    /**
     * @var OrderAddressStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderAddressStruct
    {
        return parent::get($id);
    }

    public function current(): OrderAddressStruct
    {
        return parent::current();
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (OrderAddressStruct $orderAddress) {
            return $orderAddress->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (OrderAddressStruct $orderAddress) use ($id) {
            return $orderAddress->getCountryId() === $id;
        });
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (OrderAddressStruct $orderAddress) {
            return $orderAddress->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (OrderAddressStruct $orderAddress) use ($id) {
            return $orderAddress->getCountryStateId() === $id;
        });
    }

    public function getVatIds(): array
    {
        return $this->fmap(function (OrderAddressStruct $orderAddress) {
            return $orderAddress->getVatId();
        });
    }

    public function filterByVatId(string $id): self
    {
        return $this->filter(function (OrderAddressStruct $orderAddress) use ($id) {
            return $orderAddress->getVatId() === $id;
        });
    }

    public function getCountries(): CountryCollection
    {
        return new CountryCollection(
            $this->fmap(function (OrderAddressStruct $orderAddress) {
                return $orderAddress->getCountry();
            })
        );
    }

    public function getCountryStates(): CountryStateCollection
    {
        return new CountryStateCollection(
            $this->fmap(function (OrderAddressStruct $orderAddress) {
                return $orderAddress->getCountryState();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderAddressStruct::class;
    }
}
