<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection;

use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Collection\CustomerGroupBasicCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct\CustomerGroupTranslationDetailStruct;

class CustomerGroupTranslationDetailCollection extends CustomerGroupTranslationBasicCollection
{
    /**
     * @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct\CustomerGroupTranslationDetailStruct[]
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
