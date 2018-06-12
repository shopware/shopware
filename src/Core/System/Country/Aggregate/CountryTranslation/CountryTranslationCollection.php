<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;


class CountryTranslationCollection extends EntityCollection
{
    /**
     * @var CountryTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): CountryTranslationStruct
    {
        return parent::current();
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (CountryTranslationStruct $countryTranslation) {
            return $countryTranslation->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CountryTranslationStruct $countryTranslation) use ($id) {
            return $countryTranslation->getCountryId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CountryTranslationStruct $countryTranslation) {
            return $countryTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CountryTranslationStruct $countryTranslation) use ($id) {
            return $countryTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryTranslationStruct::class;
    }
}
