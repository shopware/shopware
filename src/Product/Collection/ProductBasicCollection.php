<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Tax\Collection\TaxBasicCollection;
use Shopware\Unit\Collection\UnitBasicCollection;

class ProductBasicCollection extends EntityCollection
{
    /**
     * @var ProductBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductBasicStruct
    {
        return parent::current();
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

    public function getUnitUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getUnitUuid();
        });
    }

    public function filterByUnitUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getUnitUuid() === $uuid;
        });
    }

    public function getContainerUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getContainerUuid();
        });
    }

    public function filterByContainerUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getContainerUuid() === $uuid;
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

    public function getListingPriceUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getListingPrices()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getListingPrices(): ProductListingPriceBasicCollection
    {
        $collection = new ProductListingPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getListingPrices()->getElements());
        }

        return $collection;
    }

    public function getPriceUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPrices()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
