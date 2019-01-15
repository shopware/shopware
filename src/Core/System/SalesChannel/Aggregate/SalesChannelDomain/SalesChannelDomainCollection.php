<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SalesChannelDomainCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelDomainEntity::class;
    }
}
