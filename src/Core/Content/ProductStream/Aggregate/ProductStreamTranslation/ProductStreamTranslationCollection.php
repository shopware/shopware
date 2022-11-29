<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package business-ops
 * @extends EntityCollection<ProductStreamTranslationEntity>
 */
class ProductStreamTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getProductStreamIds(): array
    {
        return $this->fmap(function (ProductStreamTranslationEntity $productStreamTranslation) {
            return $productStreamTranslation->getProductStreamId();
        });
    }

    public function filterByProductStreamId(string $id): self
    {
        return $this->filter(function (ProductStreamTranslationEntity $productStreamTranslation) use ($id) {
            return $productStreamTranslation->getProductStreamId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductStreamTranslationEntity $productStreamTranslation) {
            return $productStreamTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductStreamTranslationEntity $productStreamTranslation) use ($id) {
            return $productStreamTranslation->getLanguageId() === $id;
        });
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
