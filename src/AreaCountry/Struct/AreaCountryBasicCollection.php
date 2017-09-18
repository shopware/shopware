<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Struct;

use Shopware\Framework\Struct\Collection;

class AreaCountryBasicCollection extends Collection
{
    /**
     * @var AreaCountryBasicStruct[]
     */
    protected $elements = [];

    public function add(AreaCountryBasicStruct $areaCountry): void
    {
        $key = $this->getKey($areaCountry);
        $this->elements[$key] = $areaCountry;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(AreaCountryBasicStruct $areaCountry): void
    {
        parent::doRemoveByKey($this->getKey($areaCountry));
    }

    public function exists(AreaCountryBasicStruct $areaCountry): bool
    {
        return parent::has($this->getKey($areaCountry));
    }

    public function getList(array $uuids): AreaCountryBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? AreaCountryBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (AreaCountryBasicStruct $areaCountry) {
            return $areaCountry->getUuid();
        });
    }

    public function getAreaUuids(): array
    {
        return $this->fmap(function (AreaCountryBasicStruct $areaCountry) {
            return $areaCountry->getAreaUuid();
        });
    }

    public function filterByAreaUuid(string $uuid): AreaCountryBasicCollection
    {
        return $this->filter(function (AreaCountryBasicStruct $areaCountry) use ($uuid) {
            return $areaCountry->getAreaUuid() === $uuid;
        });
    }

    protected function getKey(AreaCountryBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
