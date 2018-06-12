<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class CategoryTranslationBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CategoryTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CategoryTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCategoryIds(): array
    {
        return $this->fmap(function (CategoryTranslationBasicStruct $categoryTranslation) {
            return $categoryTranslation->getCategoryId();
        });
    }

    public function filterByCategoryId(string $id): self
    {
        return $this->filter(function (CategoryTranslationBasicStruct $categoryTranslation) use ($id) {
            return $categoryTranslation->getCategoryId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CategoryTranslationBasicStruct $categoryTranslation) {
            return $categoryTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CategoryTranslationBasicStruct $categoryTranslation) use ($id) {
            return $categoryTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CategoryTranslationBasicStruct::class;
    }
}
