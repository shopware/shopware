<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(CategoryTranslationEntity $entity)
 * @method void                           set(string $key, CategoryTranslationEntity $entity)
 * @method CategoryTranslationEntity[]    getIterator()
 * @method CategoryTranslationEntity[]    getElements()
 * @method CategoryTranslationEntity|null get(string $key)
 * @method CategoryTranslationEntity|null first()
 * @method CategoryTranslationEntity|null last()
 */
class CategoryTranslationCollection extends EntityCollection
{
    public function getCategoryIds(): array
    {
        return $this->fmap(function (CategoryTranslationEntity $categoryTranslation) {
            return $categoryTranslation->getCategoryId();
        });
    }

    public function filterByCategoryId(string $id): self
    {
        return $this->filter(function (CategoryTranslationEntity $categoryTranslation) use ($id) {
            return $categoryTranslation->getCategoryId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CategoryTranslationEntity $categoryTranslation) {
            return $categoryTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CategoryTranslationEntity $categoryTranslation) use ($id) {
            return $categoryTranslation->getLanguageId() === $id;
        });
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
