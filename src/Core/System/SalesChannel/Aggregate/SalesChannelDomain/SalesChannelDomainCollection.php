<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(SalesChannelDomainEntity $entity)
 * @method void                          set(string $key, SalesChannelDomainEntity $entity)
 * @method SalesChannelDomainEntity[]    getIterator()
 * @method SalesChannelDomainEntity[]    getElements()
 * @method SalesChannelDomainEntity|null get(string $key)
 * @method SalesChannelDomainEntity|null first()
 * @method SalesChannelDomainEntity|null last()
 */
class SalesChannelDomainCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_domain_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelDomainEntity::class;
    }
}
