<?php declare(strict_types=1);

namespace Shopware\Customer\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Customer\Struct\CustomerGroupDiscountBasicStruct;

class CustomerGroupDiscountBasicCollection extends EntityCollection
{
    /**
     * @var CustomerGroupDiscountBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CustomerGroupDiscountBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CustomerGroupDiscountBasicStruct
    {
        return parent::current();
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

    public function getDiscountForCartAmount(float $totalPrice, string $customerGroupUuid): ?float
    {
        $discount = null;
        foreach ($this->elements as $discountData) {
            if ($discountData->getMinimumCartAmount() > $totalPrice) {
                break;
            }
            if ($discountData->getCustomerGroupUuid() === $customerGroupUuid) {
                $discount = $discountData->getPercentageDiscount() * -1;
            }
        }

        return $discount;
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupDiscountBasicStruct::class;
    }
}
