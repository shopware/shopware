<?php declare(strict_types=1);

namespace Shopware\Holiday\Struct;

use Shopware\Framework\Struct\Collection;

class HolidayBasicCollection extends Collection
{
    /**
     * @var HolidayBasicStruct[]
     */
    protected $elements = [];

    public function add(HolidayBasicStruct $holiday): void
    {
        $key = $this->getKey($holiday);
        $this->elements[$key] = $holiday;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(HolidayBasicStruct $holiday): void
    {
        parent::doRemoveByKey($this->getKey($holiday));
    }

    public function exists(HolidayBasicStruct $holiday): bool
    {
        return parent::has($this->getKey($holiday));
    }

    public function getList(array $uuids): HolidayBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? HolidayBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (HolidayBasicStruct $holiday) {
            return $holiday->getUuid();
        });
    }

    protected function getKey(HolidayBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
