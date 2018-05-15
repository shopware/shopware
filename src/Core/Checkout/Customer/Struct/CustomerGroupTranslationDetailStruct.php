<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Api\Language\Struct\LanguageBasicStruct;

class CustomerGroupTranslationDetailStruct extends CustomerGroupTranslationBasicStruct
{
    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
