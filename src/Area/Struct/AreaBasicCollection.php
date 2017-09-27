<?php declare(strict_types=1);

namespace Shopware\Area\Struct;

use Shopware\Framework\Struct\Collection;

class AreaBasicCollection extends Collection
{
    /**
     * @var AreaBasicStruct[]
     */
    protected $elements = [];

    public function add(AreaBasicStruct $area): void
    {
        $key = $this->getKey($area);
        $this->elements[$key] = $area;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(AreaBasicStruct $area): void
    {
        parent::doRemoveByKey($this->getKey($area));
    }

    public function exists(AreaBasicStruct $area): bool
    {
        return parent::has($this->getKey($area));
    }

    public function getList(array $uuids): AreaBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? AreaBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (AreaBasicStruct $area) {
            return $area->getUuid();
        });
    }

    public function merge(AreaBasicCollection $collection)
    {
        /** @var AreaBasicStruct $area */
        foreach ($collection as $area) {
            if ($this->has($this->getKey($area))) {
                continue;
            }
            $this->add($area);
        }
    }

    protected function getKey(AreaBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
