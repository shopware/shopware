<?php declare(strict_types=1);

namespace Shopware\Country\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Country\Struct\CountryTranslationBasicStruct;

class CountryTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CountryTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CountryTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CountryTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCountryUuids(): array
    {
        return $this->fmap(function (CountryTranslationBasicStruct $countryTranslation) {
            return $countryTranslation->getCountryUuid();
        });
    }

    public function filterByCountryUuid(string $uuid): CountryTranslationBasicCollection
    {
        return $this->filter(function (CountryTranslationBasicStruct $countryTranslation) use ($uuid) {
            return $countryTranslation->getCountryUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (CountryTranslationBasicStruct $countryTranslation) {
            return $countryTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): CountryTranslationBasicCollection
    {
        return $this->filter(function (CountryTranslationBasicStruct $countryTranslation) use ($uuid) {
            return $countryTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryTranslationBasicStruct::class;
    }
}
