<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaBasicCollection;
use Shopware\Core\Content\Product\ProductBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\Pricing\PriceCollection;
use Shopware\Core\System\Tax\TaxBasicCollection;
use Shopware\Core\System\Unit\UnitBasicCollection;

class ProductBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Product\ProductBasicStruct[]
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

    public function getParentIds(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (ProductBasicStruct $product) use ($id) {
            return $product->getParentId() === $id;
        });
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

    public function getTaxes(): TaxBasicCollection
    {
        return new TaxBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getTax();
            })
        );
    }

    public function getManufacturers(): \Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerBasicCollection
    {
        return new \Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerBasicCollection(
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

    public function getPriceRuleIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPriceRules()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getPriceRules(): ProductPriceRuleBasicCollection
    {
        $collection = new ProductPriceRuleBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPriceRules()->getElements());
        }

        return $collection;
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection($this->fmap(function (ProductBasicStruct $product) {
            return $product->getPrice();
        }));
    }

    public function filterByVariationIds(array $optionIds): self
    {
        return $this->filter(function (ProductBasicStruct $product) use ($optionIds) {
            $ids = $product->getVariationIds();
            $same = array_intersect($ids, $optionIds);

            return count($same) === count($optionIds);
        });
    }

    public function getCovers(): \Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaBasicCollection
    {
        return new ProductMediaBasicCollection($this->fmap(function (ProductBasicStruct $product) {
            return $product->getCover();
        }));
    }

    protected function getExpectedClass(): string
    {
        return ProductBasicStruct::class;
    }
}
