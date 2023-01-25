<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CountryStateEntity>
 */
#[Package('system-settings')]
class CountryStateCollection extends EntityCollection
{
    public function getCountryIds(): array
    {
        return $this->fmap(fn (CountryStateEntity $countryState) => $countryState->getCountryId());
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(fn (CountryStateEntity $countryState) => $countryState->getCountryId() === $id);
    }

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryStateEntity $a, CountryStateEntity $b) {
            if ($a->getPosition() !== $b->getPosition()) {
                return $a->getPosition() <=> $b->getPosition();
            }

            if ($a->getTranslation('name') !== $b->getTranslation('name')) {
                return strnatcasecmp((string) $a->getTranslation('name'), (string) $b->getTranslation('name'));
            }

            return 0;
        });
    }

    public function getApiAlias(): string
    {
        return 'country_state_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryStateEntity::class;
    }
}
