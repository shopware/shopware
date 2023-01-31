<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductStreamTranslationEntity>
 */
#[Package('business-ops')]
class ProductStreamTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getProductStreamIds(): array
    {
        return $this->fmap(fn (ProductStreamTranslationEntity $productStreamTranslation) => $productStreamTranslation->getProductStreamId());
    }

    public function filterByProductStreamId(string $id): self
    {
        return $this->filter(fn (ProductStreamTranslationEntity $productStreamTranslation) => $productStreamTranslation->getProductStreamId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (ProductStreamTranslationEntity $productStreamTranslation) => $productStreamTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (ProductStreamTranslationEntity $productStreamTranslation) => $productStreamTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'product_stream_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamTranslationEntity::class;
    }
}
