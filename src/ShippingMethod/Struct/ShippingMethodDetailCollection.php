<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;

class ShippingMethodDetailCollection extends ShippingMethodBasicCollection
{
    /**
     * @var ShippingMethodDetailStruct[]
     */
    protected $elements = [];

    public function getCategoryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCategoryUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategories()->getElements());
        }

        return $collection;
    }

    public function getCountryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCountryUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCountries(): AreaCountryBasicCollection
    {
        $collection = new AreaCountryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCountries()->getElements());
        }

        return $collection;
    }

    public function getHolidayUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getHolidayUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getHolidays(): HolidayBasicCollection
    {
        $collection = new HolidayBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getHolidays()->getElements());
        }

        return $collection;
    }

    public function getPaymentMethodUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPaymentMethodUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        $collection = new PaymentMethodBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPaymentMethods()->getElements());
        }

        return $collection;
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
}
