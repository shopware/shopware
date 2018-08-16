<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class CatalogTranslationCollection extends EntityCollection
{
    /**
     * @var CatalogTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CatalogTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): CatalogTranslationStruct
    {
        return parent::current();
    }

    public function getCatalogIds(): array
    {
        return $this->fmap(function (CatalogTranslationStruct $catalogTranslation) {
            return $catalogTranslation->getCatalogId();
        });
    }

    public function filterByCatalogId(string $id): self
    {
        return $this->filter(function (CatalogTranslationStruct $catalogTranslation) use ($id) {
            return $catalogTranslation->getCatalogId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CatalogTranslationStruct $countryAreaTranslation) {
            return $countryAreaTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CatalogTranslationStruct $countryAreaTranslation) use ($id) {
            return $countryAreaTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CatalogTranslationStruct::class;
    }
}
