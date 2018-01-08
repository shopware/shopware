<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Collection;

use Shopware\Api\Customer\Struct\CustomerGroupTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CustomerGroupTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CustomerGroupTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CustomerGroupTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CustomerGroupTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (CustomerGroupTranslationBasicStruct $customerGroupTranslation) {
            return $customerGroupTranslation->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): self
    {
        return $this->filter(function (CustomerGroupTranslationBasicStruct $customerGroupTranslation) use ($uuid) {
            return $customerGroupTranslation->getCustomerGroupUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (CustomerGroupTranslationBasicStruct $customerGroupTranslation) {
            return $customerGroupTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): self
    {
        return $this->filter(function (CustomerGroupTranslationBasicStruct $customerGroupTranslation) use ($uuid) {
            return $customerGroupTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupTranslationBasicStruct::class;
    }
}
