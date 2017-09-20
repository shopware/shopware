<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;

class ProductDetailCollection extends ProductBasicCollection
{
    /**
     * @var ProductDetailStruct[]
     */
    protected $elements = [];

    public function getDetailUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getDetails()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getDetails(): ProductDetailBasicCollection
    {
        $collection = new ProductDetailBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getDetails()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getCategoryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCategoryUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategories()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getVoteUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getVotes()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getVotes(): ProductVoteBasicCollection
    {
        $collection = new ProductVoteBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getVotes()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
