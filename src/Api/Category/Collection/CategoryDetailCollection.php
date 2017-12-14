<?php declare(strict_types=1);

namespace Shopware\Api\Category\Collection;

use Shopware\Api\Category\Struct\CategoryDetailStruct;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class CategoryDetailCollection extends CategoryBasicCollection
{
    /**
     * @var CategoryDetailStruct[]
     */
    protected $elements = [];

    public function getParents(): CategoryBasicCollection
    {
        return new CategoryBasicCollection(
            $this->fmap(function (CategoryDetailStruct $category) {
                return $category->getParent();
            })
        );
    }

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): CategoryTranslationBasicCollection
    {
        $collection = new CategoryTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getShopUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShops()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
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

    public function getAllProductTreeUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductTreeUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAllProductTree(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductTree()->getElements());
        }

        return $collection;
    }

    public function getAllSeoProductUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getSeoProductUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAllSeoProducts(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getSeoProducts()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CategoryDetailStruct::class;
    }
}
