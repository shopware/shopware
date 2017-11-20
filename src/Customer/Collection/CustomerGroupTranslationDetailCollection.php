<?php declare(strict_types=1);

namespace Shopware\Customer\Collection;

use Shopware\Customer\Struct\CustomerGroupTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class CustomerGroupTranslationDetailCollection extends CustomerGroupTranslationBasicCollection
{
    /**
     * @var CustomerGroupTranslationDetailStruct[]
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

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
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
