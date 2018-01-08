<?php declare(strict_types=1);

namespace Shopware\Api\Country\Collection;

use Shopware\Api\Country\Struct\CountryAreaTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

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

    public function filterByCountryAreaUuid(string $uuid): self
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

    public function filterByLanguageUuid(string $uuid): self
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
