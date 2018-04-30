<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Struct;

class CustomerGroupDiscountDetailStruct extends CustomerGroupDiscountBasicStruct
{
    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    public function getCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupBasicStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }
}
