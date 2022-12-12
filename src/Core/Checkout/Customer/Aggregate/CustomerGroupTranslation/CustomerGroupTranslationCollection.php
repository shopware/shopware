<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package customer-order
 *
 * @extends EntityCollection<CustomerGroupTranslationEntity>
 */
class CustomerGroupTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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

    public function getApiAlias(): string
    {
        return 'customer_group_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupTranslationEntity::class;
    }
}
