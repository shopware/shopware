<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Product\Struct\ProductStreamDetailStruct;

class ProductStreamDetailCollection extends ProductStreamBasicCollection
{
    /**
     * @var ProductStreamDetailStruct[]
     */
    protected $elements = [];

    public function getCategoryIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCategories()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategories()->getElements());
        }

        return $collection;
    }

    public function getAllProductTabIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductTabIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getAllProductTabs(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductTabs()->getElements());
        }

        return $collection;
    }

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
