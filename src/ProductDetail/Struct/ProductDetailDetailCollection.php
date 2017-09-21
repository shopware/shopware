<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Struct;

use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;

class ProductDetailDetailCollection extends ProductDetailBasicCollection
{
    /**
     * @var ProductDetailDetailStruct[]
     */
    protected $elements = [];

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

    public function getPrices(): ProductDetailPriceBasicCollection
    {
        $collection = new ProductDetailPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPrices()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
