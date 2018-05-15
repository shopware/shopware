<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Collection;

use Shopware\Checkout\Customer\Struct\CustomerGroupDiscountDetailStruct;

class CustomerGroupDiscountDetailCollection extends CustomerGroupDiscountBasicCollection
{
    /**
     * @var CustomerGroupDiscountDetailStruct[]
     */
    protected $elements = [];

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (CustomerGroupDiscountDetailStruct $customerGroupDiscount) {
                return $customerGroupDiscount->getCustomerGroup();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupDiscountDetailStruct::class;
    }
}
