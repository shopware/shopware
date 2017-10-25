<?php declare(strict_types=1);

namespace Shopware\Tax\Struct;

use Shopware\Framework\Struct\Collection;

class TaxBasicCollection extends Collection
{
    /**
     * @var TaxBasicStruct[]
     */
    protected $elements = [];

    public function add(TaxBasicStruct $tax): void
    {
        $key = $this->getKey($tax);
        $this->elements[$key] = $tax;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(TaxBasicStruct $tax): void
    {
        parent::doRemoveByKey($this->getKey($tax));
    }

    public function exists(TaxBasicStruct $tax): bool
    {
        return parent::has($this->getKey($tax));
    }

    public function getList(array $uuids): TaxBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? TaxBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (TaxBasicStruct $tax) {
            return $tax->getUuid();
        });
    }

    public function merge(TaxBasicCollection $collection)
    {
        /** @var TaxBasicStruct $tax */
        foreach ($collection as $tax) {
            if ($this->has($this->getKey($tax))) {
                continue;
            }
            $this->add($tax);
        }
    }

    public function current(): TaxBasicStruct
    {
        return parent::current();
    }

    protected function getKey(TaxBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
