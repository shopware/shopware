<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Pricing\PriceCollection;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Unit\UnitCollection;

class ProductCollection extends EntityCollection
{
    /**
     * @var ProductEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductEntity
    {
        return parent::get($id);
    }

    public function current(): ProductEntity
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (ProductEntity $product) {
            return $product->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (ProductEntity $product) use ($id) {
            return $product->getParentId() === $id;
        });
    }

    public function getTaxIds(): array
    {
        return $this->fmap(function (ProductEntity $product) {
            return $product->getTaxId();
        });
    }

    public function filterByTaxId(string $id): self
    {
        return $this->filter(function (ProductEntity $product) use ($id) {
            return $product->getTaxId() === $id;
        });
    }

    public function getManufacturerIds(): array
    {
        return $this->fmap(function (ProductEntity $product) {
            return $product->getManufacturerId();
        });
    }

    public function filterByManufacturerId(string $id): self
    {
        return $this->filter(function (ProductEntity $product) use ($id) {
            return $product->getManufacturerId() === $id;
        });
    }

    public function getUnitIds(): array
    {
        return $this->fmap(function (ProductEntity $product) {
            return $product->getUnitId();
        });
    }

    public function filterByUnitId(string $id): self
    {
        return $this->filter(function (ProductEntity $product) use ($id) {
            return $product->getUnitId() === $id;
        });
    }

    public function getTaxes(): TaxCollection
    {
        return new TaxCollection(
            $this->fmap(function (ProductEntity $product) {
                return $product->getTax();
            })
        );
    }

    public function getManufacturers(): ProductManufacturerCollection
    {
        return new ProductManufacturerCollection(
            $this->fmap(function (ProductEntity $product) {
                return $product->getManufacturer();
            })
        );
    }

    public function getUnits(): UnitCollection
    {
        return new UnitCollection(
            $this->fmap(function (ProductEntity $product) {
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

    public function getPriceRules(): ProductPriceRuleCollection
    {
        $collection = new ProductPriceRuleCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPriceRules()->getElements());
        }

        return $collection;
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection($this->fmap(function (ProductEntity $product) {
            return $product->getPrice();
        }));
    }

    public function filterByVariationIds(array $optionIds): self
    {
        return $this->filter(function (ProductEntity $product) use ($optionIds) {
            $ids = $product->getVariationIds();
            $same = array_intersect($ids, $optionIds);

            return \count($same) === \count($optionIds);
        });
    }

    public function getCovers(): ProductMediaCollection
    {
        return new ProductMediaCollection($this->fmap(function (ProductEntity $product) {
            return $product->getCover();
        }));
    }

    protected function getExpectedClass(): string
    {
        return ProductEntity::class;
    }
}
