<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Shop\Struct\ShopBasicCollection;

class PaymentMethodDetailCollection extends PaymentMethodBasicCollection
{
    /**
     * @var PaymentMethodDetailStruct[]
     */
    protected $elements = [];

    public function getShopUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShopUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
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
}
