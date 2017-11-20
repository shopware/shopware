<?php declare(strict_types=1);

namespace Shopware\Country\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Country\Struct\CountryAreaTranslationBasicStruct;

class CountryAreaTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CountryAreaTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CountryAreaTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CountryAreaTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCountryAreaUuids(): array
    {
        return $this->fmap(function (CountryAreaTranslationBasicStruct $countryAreaTranslation) {
            return $countryAreaTranslation->getCountryAreaUuid();
        });
    }

    public function filterByCountryAreaUuid(string $uuid): CountryAreaTranslationBasicCollection
    {
        return $this->filter(function (CountryAreaTranslationBasicStruct $countryAreaTranslation) use ($uuid) {
            return $countryAreaTranslation->getCountryAreaUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (CountryAreaTranslationBasicStruct $countryAreaTranslation) {
            return $countryAreaTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): CountryAreaTranslationBasicCollection
    {
        return $this->filter(function (CountryAreaTranslationBasicStruct $countryAreaTranslation) use ($uuid) {
            return $countryAreaTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryAreaTranslationBasicStruct::class;
    }
}
