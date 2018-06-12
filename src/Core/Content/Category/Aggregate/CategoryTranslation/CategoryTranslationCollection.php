<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;


use Shopware\Core\Framework\ORM\EntityCollection;

class CategoryTranslationCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CategoryTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): CategoryTranslationStruct
    {
        return parent::current();
    }

    public function getCategoryIds(): array
    {
        return $this->fmap(function (CategoryTranslationStruct $categoryTranslation) {
            return $categoryTranslation->getCategoryId();
        });
    }

    public function filterByCategoryId(string $id): self
    {
        return $this->filter(function (CategoryTranslationStruct $categoryTranslation) use ($id) {
            return $categoryTranslation->getCategoryId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CategoryTranslationStruct $categoryTranslation) {
            return $categoryTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CategoryTranslationStruct $categoryTranslation) use ($id) {
            return $categoryTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CategoryTranslationStruct::class;
    }
}
