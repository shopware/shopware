<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductStream\Collection;

use Shopware\Content\Product\Aggregate\ProductStream\Struct\ProductStreamDetailStruct;
use Shopware\Content\Product\Collection\ProductBasicCollection;

class ProductStreamDetailCollection extends ProductStreamBasicCollection
{
    /**
     * @var \Shopware\Content\Product\Aggregate\ProductStream\Struct\ProductStreamDetailStruct[]
     */
    protected $elements = [];

    public function getAllProductIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getAllProducts(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProducts()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamDetailStruct::class;
    }
}
