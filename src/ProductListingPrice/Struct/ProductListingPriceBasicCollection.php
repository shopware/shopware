<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Struct;

use Shopware\Framework\Struct\Collection;

class ProductListingPriceBasicCollection extends Collection
{
    /**
     * @var ProductListingPriceBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductListingPriceBasicStruct $productListingPrice): void
    {
        $key = $this->getKey($productListingPrice);
        $this->elements[$key] = $productListingPrice;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductListingPriceBasicStruct $productListingPrice): void
    {
        parent::doRemoveByKey($this->getKey($productListingPrice));
    }

    public function exists(ProductListingPriceBasicStruct $productListingPrice): bool
    {
        return parent::has($this->getKey($productListingPrice));
    }

    public function getList(array $uuids): ProductListingPriceBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductListingPriceBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductListingPriceBasicStruct $productListingPrice) {
            return $productListingPrice->getUuid();
        });
    }

    public function merge(ProductListingPriceBasicCollection $collection)
    {
        /** @var ProductListingPriceBasicStruct $productListingPrice */
        foreach ($collection as $productListingPrice) {
            if ($this->has($this->getKey($productListingPrice))) {
                continue;
            }
            $this->add($productListingPrice);
        }
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductListingPriceBasicStruct $productListingPrice) {
            return $productListingPrice->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductListingPriceBasicCollection
    {
        return $this->filter(function (ProductListingPriceBasicStruct $productListingPrice) use ($uuid) {
            return $productListingPrice->getProductUuid() === $uuid;
        });
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (ProductListingPriceBasicStruct $productListingPrice) {
            return $productListingPrice->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): ProductListingPriceBasicCollection
    {
        return $this->filter(function (ProductListingPriceBasicStruct $productListingPrice) use ($uuid) {
            return $productListingPrice->getCustomerGroupUuid() === $uuid;
        });
    }

    public function current(): ProductListingPriceBasicStruct
    {
        return parent::current();
    }

    protected function getKey(ProductListingPriceBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
