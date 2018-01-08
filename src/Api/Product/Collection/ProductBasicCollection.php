<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Tax\Collection\TaxBasicCollection;
use Shopware\Api\Unit\Collection\UnitBasicCollection;

class ProductBasicCollection extends EntityCollection
{
    /**
     * @var ProductBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductBasicStruct
    {
        return parent::current();
    }

    public function getTaxIds(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getTaxId();
        });
    }

    public function filterByTaxId(string $id): self
    {
        return $this->filter(function (ProductBasicStruct $product) use ($id) {
            return $product->getTaxId() === $id;
        });
    }

    public function getManufacturerIds(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getManufacturerId();
        });
    }

    public function filterByManufacturerId(string $id): self
    {
        return $this->filter(function (ProductBasicStruct $product) use ($id) {
            return $product->getManufacturerId() === $id;
        });
    }

    public function getUnitIds(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getUnitId();
        });
    }

    public function filterByUnitId(string $id): self
    {
        return $this->filter(function (ProductBasicStruct $product) use ($id) {
            return $product->getUnitId() === $id;
        });
    }

    public function getContainerIds(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getContainerId();
        });
    }

    public function filterByContainerId(string $id): self
    {
        return $this->filter(function (ProductBasicStruct $product) use ($id) {
            return $product->getContainerId() === $id;
        });
    }

    public function getPriceGroupIds(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getPriceGroupId();
        });
    }

    public function filterByPriceGroupId(string $id): self
    {
        return $this->filter(function (ProductBasicStruct $product) use ($id) {
            return $product->getPriceGroupId() === $id;
        });
    }

    public function getTaxes(): TaxBasicCollection
    {
        return new TaxBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getTax();
            })
        );
    }

    public function getManufacturers(): ProductManufacturerBasicCollection
    {
        return new ProductManufacturerBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getManufacturer();
            })
        );
    }

    public function getUnits(): UnitBasicCollection
    {
        return new UnitBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getUnit();
            })
        );
    }

    public function getListingPriceIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getListingPrices()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getListingPrices(): ProductListingPriceBasicCollection
    {
        $collection = new ProductListingPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getListingPrices()->getElements());
        }

        return $collection;
    }

    public function getPriceIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPrices()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getPrices(): ProductPriceBasicCollection
    {
        $collection = new ProductPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPrices()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ProductBasicStruct::class;
    }
}
