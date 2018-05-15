<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Collection;

use Shopware\Checkout\Customer\Struct\CustomerGroupTranslationBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class CustomerGroupTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CustomerGroupTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerGroupTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerGroupTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (CustomerGroupTranslationBasicStruct $customerGroupTranslation) {
            return $customerGroupTranslation->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (CustomerGroupTranslationBasicStruct $customerGroupTranslation) use ($id) {
            return $customerGroupTranslation->getCustomerGroupId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CustomerGroupTranslationBasicStruct $customerGroupTranslation) {
            return $customerGroupTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CustomerGroupTranslationBasicStruct $customerGroupTranslation) use ($id) {
            return $customerGroupTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupTranslationBasicStruct::class;
    }
}
