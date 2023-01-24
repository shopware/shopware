<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package content
 * @extends EntityCollection<CategoryTranslationEntity>
 */
class CategoryTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
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
     * @return list<string>
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
