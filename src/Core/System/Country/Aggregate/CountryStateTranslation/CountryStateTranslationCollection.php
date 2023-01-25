<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CountryStateTranslationEntity>
 */
#[Package('system-settings')]
class CountryStateTranslationCollection extends EntityCollection
{
    public function getCountryStateIds(): array
    {
        return $this->fmap(fn (CountryStateTranslationEntity $countryStateTranslation) => $countryStateTranslation->getCountryStateId());
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(fn (CountryStateTranslationEntity $countryStateTranslation) => $countryStateTranslation->getCountryStateId() === $id);
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(fn (CountryStateTranslationEntity $countryStateTranslation) => $countryStateTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (CountryStateTranslationEntity $countryStateTranslation) => $countryStateTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'country_state_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryStateTranslationEntity::class;
    }
}
