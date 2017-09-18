<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\Shop\Struct\ShopBasicCollection;

class CustomerDetailCollection extends CustomerBasicCollection
{
    /**
     * @var CustomerDetailStruct[]
     */
    protected $elements = [];

    public function getAddressUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getAddressUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAddresss(): CustomerAddressBasicCollection
    {
        $collection = new CustomerAddressBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getAddresss()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (CustomerDetailStruct $customer) {
                return $customer->getShop();
            })
        );
    }
}
