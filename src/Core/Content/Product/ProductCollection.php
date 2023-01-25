<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Unit\UnitCollection;

/**
 * @extends EntityCollection<ProductEntity>
 */
#[Package('inventory')]
class ProductCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getParentIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->fmap(fn (ProductEntity $product) => $product->getParentId());

        return $ids;
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(fn (ProductEntity $product) => $product->getParentId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getTaxIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->fmap(fn (ProductEntity $product) => $product->getTaxId());

        return $ids;
    }

    public function filterByTaxId(string $id): self
    {
        return $this->filter(fn (ProductEntity $product) => $product->getTaxId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getManufacturerIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->fmap(fn (ProductEntity $product) => $product->getManufacturerId());

        return $ids;
    }

    public function filterByManufacturerId(string $id): self
    {
        return $this->filter(fn (ProductEntity $product) => $product->getManufacturerId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getUnitIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->fmap(fn (ProductEntity $product) => $product->getUnitId());

        return $ids;
    }

    public function filterByUnitId(string $id): self
    {
        return $this->filter(fn (ProductEntity $product) => $product->getUnitId() === $id);
    }

    public function getTaxes(): TaxCollection
    {
        return new TaxCollection(
            $this->fmap(fn (ProductEntity $product) => $product->getTax())
        );
    }

    public function getManufacturers(): ProductManufacturerCollection
    {
        return new ProductManufacturerCollection(
            $this->fmap(fn (ProductEntity $product) => $product->getManufacturer())
        );
    }

    public function getUnits(): UnitCollection
    {
        return new UnitCollection(
            $this->fmap(fn (ProductEntity $product) => $product->getUnit())
        );
    }

    /**
     * @return list<string>
     */
    public function getPriceIds(): array
    {
        $ids = [[]];

        foreach ($this->getIterator() as $element) {
            if ($element->getPrices() !== null) {
                $ids[] = $element->getPrices()->getIds();
            }
        }

        return array_merge(...$ids);
    }

    public function getPrices(): ProductPriceCollection
    {
        $rules = [[]];

        foreach ($this->getIterator() as $element) {
            /** @var ProductPriceCollection $prices */
            $prices = $element->getPrices();

            $rules[] = (array) $prices;
        }

        /** @var array<ProductPriceEntity> $productPriceEntities */
        $productPriceEntities = array_merge(...$rules);

        return new ProductPriceCollection($productPriceEntities);
    }

    /**
     * @param list<string> $optionIds
     */
    public function filterByOptionIds(array $optionIds): self
    {
        return $this->filter(function (ProductEntity $product) use ($optionIds) {
            $ids = $product->getOptionIds() ?? [];
            $same = array_intersect($ids, $optionIds);

            return \count($same) === \count($optionIds);
        });
    }

    public function getCovers(): ProductMediaCollection
    {
        return new ProductMediaCollection($this->fmap(fn (ProductEntity $product) => $product->getCover()));
    }

    public function getApiAlias(): string
    {
        return 'product_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductEntity::class;
    }
}
