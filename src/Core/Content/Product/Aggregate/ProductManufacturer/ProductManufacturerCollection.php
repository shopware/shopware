<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(ProductManufacturerEntity $entity)
 * @method void                           set(string $key, ProductManufacturerEntity $entity)
 * @method ProductManufacturerEntity[]    getIterator()
 * @method ProductManufacturerEntity[]    getElements()
 * @method ProductManufacturerEntity|null get(string $key)
 * @method ProductManufacturerEntity|null first()
 * @method ProductManufacturerEntity|null last()
 */
class ProductManufacturerCollection extends EntityCollection
{
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
