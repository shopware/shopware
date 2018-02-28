<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Product\Struct\ProductDetailStruct;

class ProductDetailCollection extends ProductBasicCollection
{
    /**
     * @var ProductDetailStruct[]
     */
    protected $elements = [];

    public function getParents(): ProductBasicCollection
    {
        return new ProductBasicCollection(
            $this->fmap(function (ProductDetailStruct $product) {
                return $product->getParent();
            })
        );
    }

    public function getChildrenIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getChildren()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getChildren(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getChildren()->getElements());
        }

        return $collection;
    }

    public function getMediaIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMedia()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getMedia(): ProductMediaBasicCollection
    {
        $collection = new ProductMediaBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMedia()->getElements());
        }

        return $collection;
    }

    public function getSearchKeywordIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getSearchKeywords()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getSearchKeywords(): ProductSearchKeywordBasicCollection
    {
        $collection = new ProductSearchKeywordBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getSearchKeywords()->getElements());
        }

        return $collection;
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): ProductTranslationBasicCollection
    {
        $collection = new ProductTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getAllCategories(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategories()->getElements());
        }

        return $collection;
    }

    public function getAllSeoCategories(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getSeoCategories()->getElements());
        }

        return $collection;
    }

    public function getAllTabs(): ProductStreamBasicCollection
    {
        $collection = new ProductStreamBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTabs()->getElements());
        }

        return $collection;
    }

    public function getAllStreams(): ProductStreamBasicCollection
    {
        $collection = new ProductStreamBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getStreams()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ProductDetailStruct::class;
    }
}
