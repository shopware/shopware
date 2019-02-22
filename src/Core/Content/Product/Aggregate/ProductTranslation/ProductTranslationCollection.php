<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(ProductTranslationEntity $entity)
 * @method void                          set(string $key, ProductTranslationEntity $entity)
 * @method ProductTranslationEntity[]    getIterator()
 * @method ProductTranslationEntity[]    getElements()
 * @method ProductTranslationEntity|null get(string $key)
 * @method ProductTranslationEntity|null first()
 * @method ProductTranslationEntity|null last()
 */
class ProductTranslationCollection extends EntityCollection
{
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductTranslationEntity $productTranslation) {
            return $productTranslation->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductTranslationEntity $productTranslation) use ($id) {
            return $productTranslation->getProductId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductTranslationEntity $productTranslation) {
            return $productTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductTranslationEntity $productTranslation) use ($id) {
            return $productTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductTranslationEntity::class;
    }
}
