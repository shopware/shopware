<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Struct;

use Shopware\Framework\Struct\Collection;

class AreaCountryStateBasicCollection extends Collection
{
    /**
     * @var AreaCountryStateBasicStruct[]
     */
    protected $elements = [];

    public function add(AreaCountryStateBasicStruct $areaCountryState): void
    {
        $key = $this->getKey($areaCountryState);
        $this->elements[$key] = $areaCountryState;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(AreaCountryStateBasicStruct $areaCountryState): void
    {
        parent::doRemoveByKey($this->getKey($areaCountryState));
    }

    public function exists(AreaCountryStateBasicStruct $areaCountryState): bool
    {
        return parent::has($this->getKey($areaCountryState));
    }

    public function getList(array $uuids): AreaCountryStateBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? AreaCountryStateBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (AreaCountryStateBasicStruct $areaCountryState) {
            return $areaCountryState->getUuid();
        });
    }

    public function getAreaCountryUuids(): array
    {
        return $this->fmap(function (AreaCountryStateBasicStruct $areaCountryState) {
            return $areaCountryState->getAreaCountryUuid();
        });
    }

    public function filterByAreaCountryUuid(string $uuid): AreaCountryStateBasicCollection
    {
        return $this->filter(function (AreaCountryStateBasicStruct $areaCountryState) use ($uuid) {
            return $areaCountryState->getAreaCountryUuid() === $uuid;
        });
    }

    protected function getKey(AreaCountryStateBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
