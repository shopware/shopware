<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Struct;

use Shopware\Framework\Struct\Collection;

class CustomerGroupBasicCollection extends Collection
{
    /**
     * @var CustomerGroupBasicStruct[]
     */
    protected $elements = [];

    public function add(CustomerGroupBasicStruct $customerGroup): void
    {
        $key = $this->getKey($customerGroup);
        $this->elements[$key] = $customerGroup;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(CustomerGroupBasicStruct $customerGroup): void
    {
        parent::doRemoveByKey($this->getKey($customerGroup));
    }

    public function exists(CustomerGroupBasicStruct $customerGroup): bool
    {
        return parent::has($this->getKey($customerGroup));
    }

    public function getList(array $uuids): CustomerGroupBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? CustomerGroupBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (CustomerGroupBasicStruct $customerGroup) {
            return $customerGroup->getUuid();
        });
    }

    protected function getKey(CustomerGroupBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
