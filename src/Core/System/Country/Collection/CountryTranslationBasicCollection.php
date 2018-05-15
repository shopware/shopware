<?php declare(strict_types=1);

namespace Shopware\System\Country\Collection;

use Shopware\System\Country\Struct\CountryTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CountryTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CountryTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CountryTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (CountryTranslationBasicStruct $countryTranslation) {
            return $countryTranslation->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CountryTranslationBasicStruct $countryTranslation) use ($id) {
            return $countryTranslation->getCountryId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CountryTranslationBasicStruct $countryTranslation) {
            return $countryTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CountryTranslationBasicStruct $countryTranslation) use ($id) {
            return $countryTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryTranslationBasicStruct::class;
    }
}
