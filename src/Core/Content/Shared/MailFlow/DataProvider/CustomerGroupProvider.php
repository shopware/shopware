<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\DataProvider;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<CustomerGroupEntity, CustomerGroupCollection>
 */
#[Package('after-sales')]
class CustomerGroupProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return CustomerGroupDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
