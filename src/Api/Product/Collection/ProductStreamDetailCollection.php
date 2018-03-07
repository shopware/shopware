<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Product\Struct\ProductStreamDetailStruct;

class ProductStreamDetailCollection extends ProductStreamBasicCollection
{
    /**
     * @var ProductStreamDetailStruct[]
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
