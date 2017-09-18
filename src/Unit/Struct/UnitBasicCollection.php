<?php declare(strict_types=1);

namespace Shopware\Unit\Struct;

use Shopware\Framework\Struct\Collection;

class UnitBasicCollection extends Collection
{
    /**
     * @var UnitBasicStruct[]
     */
    protected $elements = [];

    public function add(UnitBasicStruct $unit): void
    {
        $key = $this->getKey($unit);
        $this->elements[$key] = $unit;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(UnitBasicStruct $unit): void
    {
        parent::doRemoveByKey($this->getKey($unit));
    }

    public function exists(UnitBasicStruct $unit): bool
    {
        return parent::has($this->getKey($unit));
    }

    public function getList(array $uuids): UnitBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? UnitBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (UnitBasicStruct $unit) {
            return $unit->getUuid();
        });
    }

    protected function getKey(UnitBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
