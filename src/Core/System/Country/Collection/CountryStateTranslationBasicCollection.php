<?php declare(strict_types=1);

namespace Shopware\System\Country\Collection;

use Shopware\System\Country\Struct\CountryStateTranslationBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class CountryStateTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CountryStateTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryStateTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CountryStateTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (CountryStateTranslationBasicStruct $countryStateTranslation) {
            return $countryStateTranslation->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (CountryStateTranslationBasicStruct $countryStateTranslation) use ($id) {
            return $countryStateTranslation->getCountryStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CountryStateTranslationBasicStruct $countryStateTranslation) {
            return $countryStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CountryStateTranslationBasicStruct $countryStateTranslation) use ($id) {
            return $countryStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateTranslationBasicStruct::class;
    }
}
