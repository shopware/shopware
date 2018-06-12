<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation;


use Shopware\Core\Framework\ORM\EntityCollection;

class CustomerGroupTranslationCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerGroupTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerGroupTranslationStruct
    {
        return parent::current();
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (CustomerGroupTranslationStruct $customerGroupTranslation) {
            return $customerGroupTranslation->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (CustomerGroupTranslationStruct $customerGroupTranslation) use ($id) {
            return $customerGroupTranslation->getCustomerGroupId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CustomerGroupTranslationStruct $customerGroupTranslation) {
            return $customerGroupTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CustomerGroupTranslationStruct $customerGroupTranslation) use ($id) {
            return $customerGroupTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupTranslationStruct::class;
    }
}
