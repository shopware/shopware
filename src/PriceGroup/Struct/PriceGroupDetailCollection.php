<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Struct;

use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;

class PriceGroupDetailCollection extends PriceGroupBasicCollection
{
    /**
     * @var PriceGroupDetailStruct[]
     */
    protected $elements = [];

    public function getDiscountUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getDiscounts()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getDiscounts(): PriceGroupDiscountBasicCollection
    {
        $collection = new PriceGroupDiscountBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getDiscounts()->getElements());
        }

        return $collection;
    }
}
