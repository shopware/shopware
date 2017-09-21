<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Struct;

use Shopware\Framework\Struct\Collection;

class ProductDetailPriceBasicCollection extends Collection
{
    /**
     * @var ProductDetailPriceBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductDetailPriceBasicStruct $productDetailPrice): void
    {
        $key = $this->getKey($productDetailPrice);
        $this->elements[$key] = $productDetailPrice;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductDetailPriceBasicStruct $productDetailPrice): void
    {
        parent::doRemoveByKey($this->getKey($productDetailPrice));
    }

    public function exists(ProductDetailPriceBasicStruct $productDetailPrice): bool
    {
        return parent::has($this->getKey($productDetailPrice));
    }

    public function getList(array $uuids): ProductDetailPriceBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductDetailPriceBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductDetailPriceBasicStruct $productDetailPrice) {
            return $productDetailPrice->getUuid();
        });
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (ProductDetailPriceBasicStruct $productDetailPrice) {
            return $productDetailPrice->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): ProductDetailPriceBasicCollection
    {
        return $this->filter(function (ProductDetailPriceBasicStruct $productDetailPrice) use ($uuid) {
            return $productDetailPrice->getCustomerGroupUuid() === $uuid;
        });
    }

    public function getProductDetailUuids(): array
    {
        return $this->fmap(function (ProductDetailPriceBasicStruct $productDetailPrice) {
            return $productDetailPrice->getProductDetailUuid();
        });
    }

    public function filterByProductDetailUuid(string $uuid): ProductDetailPriceBasicCollection
    {
        return $this->filter(function (ProductDetailPriceBasicStruct $productDetailPrice) use ($uuid) {
            return $productDetailPrice->getProductDetailUuid() === $uuid;
        });
    }

    protected function getKey(ProductDetailPriceBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
