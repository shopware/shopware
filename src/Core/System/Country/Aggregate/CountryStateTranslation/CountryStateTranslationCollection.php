<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(CountryStateTranslationEntity $entity)
 * @method void                               set(string $key, CountryStateTranslationEntity $entity)
 * @method CountryStateTranslationEntity[]    getIterator()
 * @method CountryStateTranslationEntity[]    getElements()
 * @method CountryStateTranslationEntity|null get(string $key)
 * @method CountryStateTranslationEntity|null first()
 * @method CountryStateTranslationEntity|null last()
 */
class CountryStateTranslationCollection extends EntityCollection
{
    public function getCountryStateIds(): array
    {
        return $this->fmap(function (CountryStateTranslationEntity $countryStateTranslation) {
            return $countryStateTranslation->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (CountryStateTranslationEntity $countryStateTranslation) use ($id) {
            return $countryStateTranslation->getCountryStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CountryStateTranslationEntity $countryStateTranslation) {
            return $countryStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CountryStateTranslationEntity $countryStateTranslation) use ($id) {
            return $countryStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateTranslationEntity::class;
    }
}
