<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Struct;

use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;

class CustomerGroupDetailCollection extends CustomerGroupBasicCollection
{
    /**
     * @var CustomerGroupDetailStruct[]
     */
    protected $elements = [];

    public function getDiscountUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getDiscountUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getDiscounts(): CustomerGroupDiscountBasicCollection
    {
        $collection = new CustomerGroupDiscountBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getDiscounts()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
