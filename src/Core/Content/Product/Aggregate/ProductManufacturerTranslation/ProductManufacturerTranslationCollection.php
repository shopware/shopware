<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                      add(ProductManufacturerTranslationEntity $entity)
 * @method void                                      set(string $key, ProductManufacturerTranslationEntity $entity)
 * @method ProductManufacturerTranslationEntity[]    getIterator()
 * @method ProductManufacturerTranslationEntity[]    getElements()
 * @method ProductManufacturerTranslationEntity|null get(string $key)
 * @method ProductManufacturerTranslationEntity|null first()
 * @method ProductManufacturerTranslationEntity|null last()
 */
class ProductManufacturerTranslationCollection extends EntityCollection
{
    public function getProductManufacturerIds(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationEntity $productManufacturerTranslation) {
            return $productManufacturerTranslation->getProductManufacturerId();
        });
    }

    public function filterByProductManufacturerId(string $id): self
    {
        return $this->filter(function (ProductManufacturerTranslationEntity $productManufacturerTranslation) use ($id) {
            return $productManufacturerTranslation->getProductManufacturerId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationEntity $productManufacturerTranslation) {
            return $productManufacturerTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductManufacturerTranslationEntity $productManufacturerTranslation) use ($id) {
            return $productManufacturerTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerTranslationEntity::class;
    }
}
