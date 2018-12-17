<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductTranslationCollection extends EntityCollection
{
    /**
     * @var ProductTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): ProductTranslationEntity
    {
        return parent::current();
    }

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
