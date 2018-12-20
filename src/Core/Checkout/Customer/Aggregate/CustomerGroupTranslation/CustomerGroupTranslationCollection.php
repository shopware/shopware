<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CustomerGroupTranslationCollection extends EntityCollection
{
    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (CustomerGroupTranslationEntity $customerGroupTranslation) {
            return $customerGroupTranslation->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (CustomerGroupTranslationEntity $customerGroupTranslation) use ($id) {
            return $customerGroupTranslation->getCustomerGroupId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CustomerGroupTranslationEntity $customerGroupTranslation) {
            return $customerGroupTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CustomerGroupTranslationEntity $customerGroupTranslation) use ($id) {
            return $customerGroupTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupTranslationEntity::class;
    }
}
