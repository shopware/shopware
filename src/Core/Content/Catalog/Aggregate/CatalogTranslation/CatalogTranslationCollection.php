<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CatalogTranslationCollection extends EntityCollection
{
    /**
     * @var CatalogTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? CatalogTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): CatalogTranslationEntity
    {
        return parent::current();
    }

    public function getCatalogIds(): array
    {
        return $this->fmap(function (CatalogTranslationEntity $catalogTranslation) {
            return $catalogTranslation->getCatalogId();
        });
    }

    public function filterByCatalogId(string $id): self
    {
        return $this->filter(function (CatalogTranslationEntity $catalogTranslation) use ($id) {
            return $catalogTranslation->getCatalogId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CatalogTranslationEntity $catalogTranslation) {
            return $catalogTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CatalogTranslationEntity $catalogTranslation) use ($id) {
            return $catalogTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CatalogTranslationEntity::class;
    }
}
