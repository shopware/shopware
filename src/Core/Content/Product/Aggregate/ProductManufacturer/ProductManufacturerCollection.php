<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductManufacturerEntity>
 */
#[Package('inventory')]
class ProductManufacturerCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(fn (ProductManufacturerEntity $productManufacturer) => $productManufacturer->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(fn (ProductManufacturerEntity $productManufacturer) => $productManufacturer->getMediaId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'product_manufacturer_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerEntity::class;
    }
}
