<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Struct;

use Shopware\Framework\Struct\Collection;

class CustomerGroupDiscountBasicCollection extends Collection
{
    /**
     * @var CustomerGroupDiscountBasicStruct[]
     */
    protected $elements = [];

    public function add(CustomerGroupDiscountBasicStruct $customerGroupDiscount): void
    {
        $key = $this->getKey($customerGroupDiscount);
        $this->elements[$key] = $customerGroupDiscount;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(CustomerGroupDiscountBasicStruct $customerGroupDiscount): void
    {
        parent::doRemoveByKey($this->getKey($customerGroupDiscount));
    }

    public function exists(CustomerGroupDiscountBasicStruct $customerGroupDiscount): bool
    {
        return parent::has($this->getKey($customerGroupDiscount));
    }

    public function getList(array $uuids): CustomerGroupDiscountBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? CustomerGroupDiscountBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (CustomerGroupDiscountBasicStruct $customerGroupDiscount) {
            return $customerGroupDiscount->getUuid();
        });
    }

    public function merge(CustomerGroupDiscountBasicCollection $collection)
    {
        /** @var CustomerGroupDiscountBasicStruct $customerGroupDiscount */
        foreach ($collection as $customerGroupDiscount) {
            if ($this->has($this->getKey($customerGroupDiscount))) {
                continue;
            }
            $this->add($customerGroupDiscount);
        }
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (CustomerGroupDiscountBasicStruct $customerGroupDiscount) {
            return $customerGroupDiscount->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): CustomerGroupDiscountBasicCollection
    {
        return $this->filter(function (CustomerGroupDiscountBasicStruct $customerGroupDiscount) use ($uuid) {
            return $customerGroupDiscount->getCustomerGroupUuid() === $uuid;
        });
    }

    public function current(): CustomerGroupDiscountBasicStruct
    {
        return parent::current();
    }

    protected function getKey(CustomerGroupDiscountBasicStruct $element): string
    {
        return $element->getUuid();
    }

    public function getDiscountForCartAmount(float $totalPrice, string $customerGroupUuid): ?float
    {
        $discount = null;
        foreach ($this->elements as $discountData) {
            if ($discountData->getMinimumCartAmount() > $totalPrice) {
                return $discount;
            }
            if ($discountData->getCustomerGroupUuid() === $customerGroupUuid) {
                $discount = $discountData->getPercentageDiscount() * -1;
            }
        }
        return null;
    }
}
