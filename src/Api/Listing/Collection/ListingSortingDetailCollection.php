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

    public function getTranslations(): ListingSortingTranslationBasicCollection
    {
        $collection = new ListingSortingTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getProductStreamUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductStreams()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
