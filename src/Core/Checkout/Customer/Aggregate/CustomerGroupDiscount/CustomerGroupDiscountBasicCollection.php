<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\CustomerGroupDiscountBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class CustomerGroupDiscountBasicCollection extends EntityCollection
{
    /**
     * @var CustomerGroupDiscountBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerGroupDiscountBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerGroupDiscountBasicStruct
    {
        return parent::current();
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (CustomerGroupDiscountBasicStruct $customerGroupDiscount) {
            return $customerGroupDiscount->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (CustomerGroupDiscountBasicStruct $customerGroupDiscount) use ($id) {
            return $customerGroupDiscount->getCustomerGroupId() === $id;
        });
    }

    public function getDiscountForCartAmount(float $totalPrice, string $customerGroupId): ?float
    {
        $discount = null;
        foreach ($this->elements as $discountData) {
            if ($discountData->getMinimumCartAmount() > $totalPrice) {
                break;
            }
            if ($discountData->getCustomerGroupId() === $customerGroupId) {
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
