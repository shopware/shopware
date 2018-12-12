<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CountryStateCollection extends EntityCollection
{
    /**
     * @var CountryStateEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryStateEntity
    {
        return parent::get($id);
    }

    public function current(): CountryStateEntity
    {
        return parent::current();
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (CountryStateEntity $countryState) {
            return $countryState->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CountryStateEntity $countryState) use ($id) {
            return $countryState->getCountryId() === $id;
        });
    }

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryStateEntity $a, CountryStateEntity $b) {
            if ($a->getPosition() !== $b->getPosition()) {
                return $a->getPosition() <=> $b->getPosition();
            }

            if ($a->getName() !== $b->getName()) {
                return strnatcasecmp($a->getName(), $b->getName());
            }

            return 0;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateEntity::class;
    }
}
