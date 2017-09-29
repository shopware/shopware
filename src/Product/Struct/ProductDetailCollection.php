<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;

class ProductDetailCollection extends ProductBasicCollection
{
    /**
     * @var ProductDetailStruct[]
     */
    protected $elements = [];

    public function getMediaUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMedia()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getMedia(): ProductMediaBasicCollection
    {
        $collection = new ProductMediaBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMedia()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

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

    public function getCategoryTreeUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCategoryTreeUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCategoryTree(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategoryTree()->getIterator()->getArrayCopy());
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

    public function getVoteAverageUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getVoteAverages()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getVoteAverages(): ProductVoteAverageBasicCollection
    {
        $collection = new ProductVoteAverageBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getVoteAverages()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
