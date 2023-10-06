<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductTranslationEntity>
 */
#[Package('inventory')]
class ProductTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(fn (ProductTranslationEntity $productTranslation) => $productTranslation->getProductId());
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(fn (ProductTranslationEntity $productTranslation) => $productTranslation->getProductId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (ProductTranslationEntity $productTranslation) => $productTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (ProductTranslationEntity $productTranslation) => $productTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'product_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductTranslationEntity::class;
    }
}
