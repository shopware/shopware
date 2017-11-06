<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Struct;

use Shopware\Framework\Struct\Collection;

class ProductPriceBasicCollection extends Collection
{
    /**
     * @var ProductPriceBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductPriceBasicStruct $productPrice): void
    {
        $key = $this->getKey($productPrice);
        $this->elements[$key] = $productPrice;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductPriceBasicStruct $productPrice): void
    {
        parent::doRemoveByKey($this->getKey($productPrice));
    }

    public function exists(ProductPriceBasicStruct $productPrice): bool
    {
        return parent::has($this->getKey($productPrice));
    }

    public function getList(array $uuids): ProductPriceBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductPriceBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getUuid();
        });
    }

    public function merge(ProductPriceBasicCollection $collection)
    {
        /** @var ProductPriceBasicStruct $productPrice */
        foreach ($collection as $productPrice) {
            if ($this->has($this->getKey($productPrice))) {
                continue;
            }
            $this->add($productPrice);
        }
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): ProductPriceBasicCollection
    {
        return $this->filter(function (ProductPriceBasicStruct $productPrice) use ($uuid) {
            return $productPrice->getCustomerGroupUuid() === $uuid;
        });
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductPriceBasicCollection
    {
        return $this->filter(function (ProductPriceBasicStruct $productPrice) use ($uuid) {
            return $productPrice->getProductUuid() === $uuid;
        });
    }

    public function current(): ProductPriceBasicStruct
    {
        return parent::current();
    }

    protected function getKey(ProductPriceBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
