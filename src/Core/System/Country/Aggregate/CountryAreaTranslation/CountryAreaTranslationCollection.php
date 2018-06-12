<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryAreaTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;


class CountryAreaTranslationCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\CountryAreaTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryAreaTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): CountryAreaTranslationStruct
    {
        return parent::current();
    }

    public function getCountryAreaIds(): array
    {
        return $this->fmap(function (CountryAreaTranslationStruct $countryAreaTranslation) {
            return $countryAreaTranslation->getCountryAreaId();
        });
    }

    public function filterByCountryAreaId(string $id): self
    {
        return $this->filter(function (CountryAreaTranslationStruct $countryAreaTranslation) use ($id) {
            return $countryAreaTranslation->getCountryAreaId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CountryAreaTranslationStruct $countryAreaTranslation) {
            return $countryAreaTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CountryAreaTranslationStruct $countryAreaTranslation) use ($id) {
            return $countryAreaTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryAreaTranslationStruct::class;
    }
}
