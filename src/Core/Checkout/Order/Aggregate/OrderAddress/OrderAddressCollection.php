<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\CountryCollection;

/**
 * @method void                    add(OrderAddressEntity $entity)
 * @method void                    set(string $key, OrderAddressEntity $entity)
 * @method OrderAddressEntity[]    getIterator()
 * @method OrderAddressEntity[]    getElements()
 * @method OrderAddressEntity|null get(string $key)
 * @method OrderAddressEntity|null first()
 * @method OrderAddressEntity|null last()
 */
class OrderAddressCollection extends EntityCollection
{
    public function getCountryIds(): array
    {
        return $this->fmap(function (OrderAddressEntity $orderAddress) {
            return $orderAddress->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (OrderAddressEntity $orderAddress) use ($id) {
            return $orderAddress->getCountryId() === $id;
        });
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (OrderAddressEntity $orderAddress) {
            return $orderAddress->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (OrderAddressEntity $orderAddress) use ($id) {
            return $orderAddress->getCountryStateId() === $id;
        });
    }

    public function getVatIds(): array
    {
        return $this->fmap(function (OrderAddressEntity $orderAddress) {
            return $orderAddress->getVatId();
        });
    }

    public function filterByVatId(string $id): self
    {
        return $this->filter(function (OrderAddressEntity $orderAddress) use ($id) {
            return $orderAddress->getVatId() === $id;
        });
    }

    public function getCountries(): CountryCollection
    {
        return new CountryCollection(
            $this->fmap(function (OrderAddressEntity $orderAddress) {
                return $orderAddress->getCountry();
            })
        );
    }

    public function getCountryStates(): CountryStateCollection
    {
        return new CountryStateCollection(
            $this->fmap(function (OrderAddressEntity $orderAddress) {
                return $orderAddress->getCountryState();
            })
        );
    }

    public function getApiAlias(): string
    {
        return 'order_address_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderAddressEntity::class;
    }
}
