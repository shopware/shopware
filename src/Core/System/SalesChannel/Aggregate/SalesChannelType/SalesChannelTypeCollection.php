<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @extends EntityCollection<SalesChannelTypeEntity>
 */
#[Package('sales-channel')]
class SalesChannelTypeCollection extends EntityCollection
{
    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(fn (SalesChannelTypeEntity $salesChannel) => $salesChannel->getSalesChannels())
        );
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTypeEntity::class;
    }
}
