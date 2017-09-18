<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Framework\Struct\Collection;
use Shopware\PriceGroup\Struct\PriceGroupBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicCollection;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;
use Shopware\Tax\Struct\TaxBasicCollection;

class ProductBasicCollection extends Collection
{
    /**
     * @var ProductBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductBasicStruct $product): void
    {
        $key = $this->getKey($product);
        $this->elements[$key] = $product;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductBasicStruct $product): void
    {
        parent::doRemoveByKey($this->getKey($product));
    }

    public function exists(ProductBasicStruct $product): bool
    {
        return parent::has($this->getKey($product));
    }

    public function getList(array $uuids): ProductBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getUuid();
        });
    }

    public function getTaxUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getTaxUuid();
        });
    }

    public function filterByTaxUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getTaxUuid() === $uuid;
        });
    }

    public function getManufacturerUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getManufacturerUuid();
        });
    }

    public function filterByManufacturerUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getManufacturerUuid() === $uuid;
        });
    }

    public function getPriceGroupUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getPriceGroupUuid();
        });
    }

    public function filterByPriceGroupUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getPriceGroupUuid() === $uuid;
        });
    }

    public function getFilterGroupUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getFilterGroupUuid();
        });
    }

    public function filterByFilterGroupUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getFilterGroupUuid() === $uuid;
        });
    }

    public function getMainDetailUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getMainDetailUuid();
        });
    }

    public function filterByMainDetailUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getMainDetailUuid() === $uuid;
        });
    }

    public function getManufacturers(): ProductManufacturerBasicCollection
    {
        return new ProductManufacturerBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getManufacturer();
            })
        );
    }

    public function getMainDetails(): ProductDetailBasicCollection
    {
        return new ProductDetailBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getMainDetail();
            })
        );
    }

    public function getTaxs(): TaxBasicCollection
    {
        return new TaxBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getTax();
            })
        );
    }

    public function getCanonicalUrls(): SeoUrlBasicCollection
    {
        return new SeoUrlBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getCanonicalUrl();
            })
        );
    }

    public function getPriceGroups(): PriceGroupBasicCollection
    {
        return new PriceGroupBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getPriceGroup();
            })
        );
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

    public function getBlockedCustomerGroupss(): CustomerGroupBasicCollection
    {
        $collection = new CustomerGroupBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getBlockedCustomerGroupss()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    protected function getKey(ProductBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
