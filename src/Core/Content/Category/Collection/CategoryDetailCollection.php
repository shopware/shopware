<?php declare(strict_types=1);

namespace Shopware\Content\Category\Collection;

use Shopware\Content\Category\Struct\CategoryDetailStruct;
use Shopware\Content\Media\Collection\MediaBasicCollection;
use Shopware\Content\Product\Aggregate\ProductStream\Collection\ProductStreamBasicCollection;

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

    public function getChildren(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getChildren()->getElements());
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

    public function getTranslations(): CategoryTranslationBasicCollection
    {
        $collection = new CategoryTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getMedia(): MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (CategoryDetailStruct $category) {
                return $category->getMedia();
            })
        );
    }

    public function getProductStreams(): ProductStreamBasicCollection
    {
        return new ProductStreamBasicCollection(
            $this->fmap(function (CategoryDetailStruct $category) {
                return $category->getProductStream();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CategoryDetailStruct::class;
    }
}
