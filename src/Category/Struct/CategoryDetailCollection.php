<?php declare(strict_types=1);

namespace Shopware\Category\Struct;

use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Media\Struct\MediaBasicCollection;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\ProductStream\Struct\ProductStreamBasicCollection;

class CategoryDetailCollection extends CategoryBasicCollection
{
    /**
     * @var CategoryDetailStruct[]
     */
    protected $elements = [];

    public function getProductStreams(): ProductStreamBasicCollection
    {
        return new ProductStreamBasicCollection(
            $this->fmap(function (CategoryDetailStruct $category) {
                return $category->getProductStream();
            })
        );
    }

    public function getMedia(): MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (CategoryDetailStruct $category) {
                return $category->getMedia();
            })
        );
    }

    public function getProductUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProducts(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProducts()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getBlockedCustomerGroupsUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getBlockedCustomerGroupsUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getBlockedCustomerGroups(): CustomerGroupBasicCollection
    {
        $collection = new CustomerGroupBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getBlockedCustomerGroups()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
