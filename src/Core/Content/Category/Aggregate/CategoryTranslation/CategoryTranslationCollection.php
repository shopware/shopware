<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

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

    protected function getExpectedClass(): string
    {
        return CategoryTranslationEntity::class;
    }
}
