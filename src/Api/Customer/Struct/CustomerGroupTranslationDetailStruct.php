<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class CustomerGroupTranslationDetailStruct extends CustomerGroupTranslationBasicStruct
{
    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupBasicStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
