<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductManufacturerEntity>
 *
 * @package inventory
 */
class ProductManufacturerCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(function (ProductManufacturerEntity $productManufacturer) {
            return $productManufacturer->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ProductManufacturerEntity $productManufacturer) use ($id) {
            return $productManufacturer->getMediaId() === $id;
        });
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
