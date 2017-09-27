<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Struct;

use Shopware\Framework\Struct\Collection;

class PriceGroupBasicCollection extends Collection
{
    /**
     * @var PriceGroupBasicStruct[]
     */
    protected $elements = [];

    public function add(PriceGroupBasicStruct $priceGroup): void
    {
        $key = $this->getKey($priceGroup);
        $this->elements[$key] = $priceGroup;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(PriceGroupBasicStruct $priceGroup): void
    {
        parent::doRemoveByKey($this->getKey($priceGroup));
    }

    public function exists(PriceGroupBasicStruct $priceGroup): bool
    {
        return parent::has($this->getKey($priceGroup));
    }

    public function getList(array $uuids): PriceGroupBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? PriceGroupBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (PriceGroupBasicStruct $priceGroup) {
            return $priceGroup->getUuid();
        });
    }

    public function merge(PriceGroupBasicCollection $collection)
    {
        /** @var PriceGroupBasicStruct $priceGroup */
        foreach ($collection as $priceGroup) {
            if ($this->has($this->getKey($priceGroup))) {
                continue;
            }
            $this->add($priceGroup);
        }
    }

    protected function getKey(PriceGroupBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
