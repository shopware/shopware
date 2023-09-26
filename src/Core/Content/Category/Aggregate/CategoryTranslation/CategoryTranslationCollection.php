<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CategoryTranslationEntity>
 */
#[Package('inventory')]
class CategoryTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getCategoryIds(): array
    {
        return $this->fmap(fn (CategoryTranslationEntity $categoryTranslation) => $categoryTranslation->getCategoryId());
    }

    public function filterByCategoryId(string $id): self
    {
        return $this->filter(fn (CategoryTranslationEntity $categoryTranslation) => $categoryTranslation->getCategoryId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (CategoryTranslationEntity $categoryTranslation) => $categoryTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (CategoryTranslationEntity $categoryTranslation) => $categoryTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'category_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryTranslationEntity::class;
    }
}
