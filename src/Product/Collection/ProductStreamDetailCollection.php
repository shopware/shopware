<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Product\Struct\ProductStreamDetailStruct;

class ProductStreamDetailCollection extends ProductStreamBasicCollection
{
    /**
     * @var ProductStreamDetailStruct[]
     */
    protected $elements = [];

    public function getAllProductTabUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductTabUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAllProductTabs(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductTabs()->getElements());
        }

        return $collection;
    }

    public function getAllProductUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
