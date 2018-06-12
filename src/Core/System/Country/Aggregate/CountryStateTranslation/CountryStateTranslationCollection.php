<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;


class CountryStateTranslationCollection extends EntityCollection
{
    /**
     * @var CountryStateTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryStateTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): CountryStateTranslationStruct
    {
        return parent::current();
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (CountryStateTranslationStruct $countryStateTranslation) {
            return $countryStateTranslation->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (CountryStateTranslationStruct $countryStateTranslation) use ($id) {
            return $countryStateTranslation->getCountryStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CountryStateTranslationStruct $countryStateTranslation) {
            return $countryStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CountryStateTranslationStruct $countryStateTranslation) use ($id) {
            return $countryStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateTranslationStruct::class;
    }
}
