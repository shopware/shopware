<?php declare(strict_types=1);

namespace Shopware\Api\Country\Collection;

use Shopware\Api\Country\Struct\CountryStateTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CountryStateTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CountryStateTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CountryStateTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CountryStateTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCountryStateUuids(): array
    {
        return $this->fmap(function (CountryStateTranslationBasicStruct $countryStateTranslation) {
            return $countryStateTranslation->getCountryStateUuid();
        });
    }

    public function filterByCountryStateUuid(string $uuid): self
    {
        return $this->filter(function (CountryStateTranslationBasicStruct $countryStateTranslation) use ($uuid) {
            return $countryStateTranslation->getCountryStateUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (CountryStateTranslationBasicStruct $countryStateTranslation) {
            return $countryStateTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): self
    {
        return $this->filter(function (CountryStateTranslationBasicStruct $countryStateTranslation) use ($uuid) {
            return $countryStateTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateTranslationBasicStruct::class;
    }
}
