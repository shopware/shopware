<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
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
     * @return array<string>
     */
    public function getParentIds(): array
    {
        return $this->fmap(fn (ProductEntity $product) => $product->getParentId());
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(fn (ProductEntity $product) => $product->getParentId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getTaxIds(): array
    {
        return $this->fmap(fn (ProductEntity $product) => $product->getTaxId());
    }

    public function filterByTaxId(string $id): self
    {
        return $this->filter(fn (ProductEntity $product) => $product->getTaxId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getManufacturerIds(): array
    {
        return $this->fmap(fn (ProductEntity $product) => $product->getManufacturerId());
    }

    public function filterByManufacturerId(string $id): self
    {
        return $this->filter(fn (ProductEntity $product) => $product->getManufacturerId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getUnitIds(): array
    {
        return $this->fmap(fn (ProductEntity $product) => $product->getUnitId());
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
     * @return array<string>
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
        $priceArray = [[]];

        foreach ($this->elements as $element) {
            $prices = $element->getPrices();
            if ($prices) {
                $priceArray[] = $prices->getElements();
            }
        }

        $productPriceEntities = array_merge(...$priceArray);

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
