<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Collection;

use Shopware\Checkout\Customer\Aggregate\CustomerGroup\Collection\CustomerGroupBasicCollection;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Struct\CustomerGroupDiscountDetailStruct;

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
