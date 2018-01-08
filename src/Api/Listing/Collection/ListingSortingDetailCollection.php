<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Collection;

use Shopware\Api\Listing\Struct\ListingSortingDetailStruct;
use Shopware\Api\Product\Collection\ProductStreamBasicCollection;

class ListingSortingDetailCollection extends ListingSortingBasicCollection
{
    /**
     * @var ListingSortingDetailStruct[]
     */
    protected $elements = [];

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

    public function getTranslations(): ListingSortingTranslationBasicCollection
    {
        $collection = new ListingSortingTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getProductStreamIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductStreams()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getProductStreams(): ProductStreamBasicCollection
    {
        $collection = new ProductStreamBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductStreams()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingDetailStruct::class;
    }
}
