<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;
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
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     *
     * @return array<string>
     */
    public function getParentIds(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->fmap(fn (ProductEntity $product) => $product->getParentId());
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function filterByParentId(string $id): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->filter(fn (ProductEntity $product) => $product->getParentId() === $id);
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     *
     * @return array<string>
     */
    public function getTaxIds(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->fmap(fn (ProductEntity $product) => $product->getTaxId());
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function filterByTaxId(string $id): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->filter(fn (ProductEntity $product) => $product->getTaxId() === $id);
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     *
     * @return array<string>
     */
    public function getManufacturerIds(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->fmap(fn (ProductEntity $product) => $product->getManufacturerId());
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function filterByManufacturerId(string $id): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->filter(fn (ProductEntity $product) => $product->getManufacturerId() === $id);
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     *
     * @return array<string>
     */
    public function getUnitIds(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->fmap(fn (ProductEntity $product) => $product->getUnitId());
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function filterByUnitId(string $id): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->filter(fn (ProductEntity $product) => $product->getUnitId() === $id);
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function getTaxes(): TaxCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return new TaxCollection(
            $this->fmap(fn (ProductEntity $product) => $product->getTax())
        );
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function getManufacturers(): ProductManufacturerCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return new ProductManufacturerCollection(
            $this->fmap(fn (ProductEntity $product) => $product->getManufacturer())
        );
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function getUnits(): UnitCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return new UnitCollection(
            $this->fmap(fn (ProductEntity $product) => $product->getUnit())
        );
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     *
     * @return array<string>
     */
    public function getPriceIds(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
        $ids = [[]];

        foreach ($this->getIterator() as $element) {
            if ($element->getPrices() !== null) {
                $ids[] = $element->getPrices()->getIds();
            }
        }

        return array_merge(...$ids);
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function getPrices(): ProductPriceCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
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
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     *
     * @param list<string> $optionIds
     */
    public function filterByOptionIds(array $optionIds): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return $this->filter(function (ProductEntity $product) use ($optionIds) {
            $ids = $product->getOptionIds() ?? [];
            $same = array_intersect($ids, $optionIds);

            return \count($same) === \count($optionIds);
        });
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement
     */
    public function getCovers(): ProductMediaCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

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
