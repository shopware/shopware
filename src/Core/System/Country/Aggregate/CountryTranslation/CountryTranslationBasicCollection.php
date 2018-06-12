<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationBasicStruct;

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
