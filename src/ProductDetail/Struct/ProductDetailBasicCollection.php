<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Struct;

use Shopware\Framework\Struct\Collection;
use Shopware\Unit\Struct\UnitBasicCollection;

class ProductDetailBasicCollection extends Collection
{
    /**
     * @var ProductDetailBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductDetailBasicStruct $productDetail): void
    {
        $key = $this->getKey($productDetail);
        $this->elements[$key] = $productDetail;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductDetailBasicStruct $productDetail): void
    {
        parent::doRemoveByKey($this->getKey($productDetail));
    }

    public function exists(ProductDetailBasicStruct $productDetail): bool
    {
        return parent::has($this->getKey($productDetail));
    }

    public function getList(array $uuids): ProductDetailBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductDetailBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductDetailBasicStruct $productDetail) {
            return $productDetail->getUuid();
        });
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductDetailBasicStruct $productDetail) {
            return $productDetail->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductDetailBasicCollection
    {
        return $this->filter(function (ProductDetailBasicStruct $productDetail) use ($uuid) {
            return $productDetail->getProductUuid() === $uuid;
        });
    }

    public function getUnitUuids(): array
    {
        return $this->fmap(function (ProductDetailBasicStruct $productDetail) {
            return $productDetail->getUnitUuid();
        });
    }

    public function filterByUnitUuid(string $uuid): ProductDetailBasicCollection
    {
        return $this->filter(function (ProductDetailBasicStruct $productDetail) use ($uuid) {
            return $productDetail->getUnitUuid() === $uuid;
        });
    }

    public function getUnits(): UnitBasicCollection
    {
        return new UnitBasicCollection(
            $this->fmap(function (ProductDetailBasicStruct $productDetail) {
                return $productDetail->getUnit();
            })
        );
    }

    protected function getKey(ProductDetailBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
