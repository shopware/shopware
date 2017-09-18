<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Struct;

use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;

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
            foreach ($element->getPriceUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getPrices(): ProductPriceBasicCollection
    {
        $collection = new ProductPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPrices()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
