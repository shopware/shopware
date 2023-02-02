<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CountryTranslationEntity>
 */
class CountryTranslationCollection extends EntityCollection
{
    public function getCountryIds(): array
    {
        return $this->fmap(function (CountryTranslationEntity $countryTranslation) {
            return $countryTranslation->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CountryTranslationEntity $countryTranslation) use ($id) {
            return $countryTranslation->getCountryId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CountryTranslationEntity $countryTranslation) {
            return $countryTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CountryTranslationEntity $countryTranslation) use ($id) {
            return $countryTranslation->getLanguageId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'country_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryTranslationEntity::class;
    }
}
