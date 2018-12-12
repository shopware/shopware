<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CountryCollection extends EntityCollection
{
    /**
     * @var CountryEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryEntity
    {
        return parent::get($id);
    }

    public function current(): CountryEntity
    {
        return parent::current();
    }

    public function getTaxfreeForVatIds(): array
    {
        return $this->fmap(function (CountryEntity $country) {
            return $country->getTaxfreeForVatId();
        });
    }

    public function filterByTaxfreeForVatId(bool $vatFree): self
    {
        return $this->filter(function (CountryEntity $country) use ($vatFree) {
            return $country->getTaxfreeForVatId() === $vatFree;
        });
    }

    public function sortCountryAndStates(): void
    {
        $this->sortByPositionAndName();
        foreach ($this->elements as $country) {
            if ($country->getStates()) {
                $country->getStates()->sortByPositionAndName();
            }
        }
    }

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryEntity $a, CountryEntity $b) {
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
        return CountryEntity::class;
    }
}
