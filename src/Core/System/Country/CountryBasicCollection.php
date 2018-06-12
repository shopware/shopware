<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Country\CountryBasicStruct;

class CountryBasicCollection extends EntityCollection
{
    /**
     * @var CountryBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CountryBasicStruct
    {
        return parent::current();
    }

    public function getAreaIds(): array
    {
        return $this->fmap(function (CountryBasicStruct $country) {
            return $country->getAreaId();
        });
    }

    public function filterByAreaId(string $id): self
    {
        return $this->filter(function (CountryBasicStruct $country) use ($id) {
            return $country->getAreaId() === $id;
        });
    }

    public function getTaxfreeForVatIds(): array
    {
        return $this->fmap(function (CountryBasicStruct $country) {
            return $country->getTaxfreeForVatId();
        });
    }

    public function filterByTaxfreeForVatId(string $id): self
    {
        return $this->filter(function (CountryBasicStruct $country) use ($id) {
            return $country->getTaxfreeForVatId() === $id;
        });
    }

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryBasicStruct $a, CountryBasicStruct $b) {
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
        return CountryBasicStruct::class;
    }
}
