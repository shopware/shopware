<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection;

use Shopware\System\Language\Collection\LanguageBasicCollection;
use Shopware\Checkout\Customer\Aggregate\CustomerGroup\Collection\CustomerGroupBasicCollection;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct\CustomerGroupTranslationDetailStruct;

class CustomerGroupTranslationDetailCollection extends CustomerGroupTranslationBasicCollection
{
    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct\CustomerGroupTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (CustomerGroupTranslationDetailStruct $customerGroupTranslation) {
                return $customerGroupTranslation->getCustomerGroup();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (CustomerGroupTranslationDetailStruct $customerGroupTranslation) {
                return $customerGroupTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupTranslationDetailStruct::class;
    }
}
