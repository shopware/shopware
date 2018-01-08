<?php declare(strict_types=1);

namespace Shopware\Api\Category\Collection;

use Shopware\Api\Category\Struct\CategoryTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CategoryTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CategoryTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CategoryTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CategoryTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCategoryUuids(): array
    {
        return $this->fmap(function (CategoryTranslationBasicStruct $categoryTranslation) {
            return $categoryTranslation->getCategoryUuid();
        });
    }

    public function filterByCategoryUuid(string $uuid): self
    {
        return $this->filter(function (CategoryTranslationBasicStruct $categoryTranslation) use ($uuid) {
            return $categoryTranslation->getCategoryUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (CategoryTranslationBasicStruct $categoryTranslation) {
            return $categoryTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): self
    {
        return $this->filter(function (CategoryTranslationBasicStruct $categoryTranslation) use ($uuid) {
            return $categoryTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return CategoryTranslationBasicStruct::class;
    }
}
