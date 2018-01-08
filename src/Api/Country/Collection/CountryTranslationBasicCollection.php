<?php declare(strict_types=1);

namespace Shopware\Api\Country\Collection;

use Shopware\Api\Country\Struct\CountryTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

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

    public function filterByCountryUuid(string $uuid): self
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

    public function filterByLanguageUuid(string $uuid): self
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
