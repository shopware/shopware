<?php declare(strict_types=1);

namespace Shopware\Currency\Struct;

use Shopware\Shop\Struct\ShopBasicCollection;

class CurrencyDetailCollection extends CurrencyBasicCollection
{
    /**
     * @var CurrencyDetailStruct[]
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
            $collection->fill($element->getShops()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
