<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Struct;

use Shopware\Framework\Struct\Collection;

class ListingSortingBasicCollection extends Collection
{
    /**
     * @var ListingSortingBasicStruct[]
     */
    protected $elements = [];

    public function add(ListingSortingBasicStruct $listingSorting): void
    {
        $key = $this->getKey($listingSorting);
        $this->elements[$key] = $listingSorting;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ListingSortingBasicStruct $listingSorting): void
    {
        parent::doRemoveByKey($this->getKey($listingSorting));
    }

    public function exists(ListingSortingBasicStruct $listingSorting): bool
    {
        return parent::has($this->getKey($listingSorting));
    }

    public function getList(array $uuids): ListingSortingBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ListingSortingBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ListingSortingBasicStruct $listingSorting) {
            return $listingSorting->getUuid();
        });
    }

    protected function getKey(ListingSortingBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
