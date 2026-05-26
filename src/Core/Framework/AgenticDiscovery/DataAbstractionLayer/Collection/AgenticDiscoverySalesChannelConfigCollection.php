<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Collection;

use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity\AgenticDiscoverySalesChannelConfigEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @extends EntityCollection<AgenticDiscoverySalesChannelConfigEntity>
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoverySalesChannelConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'agentic_discovery_sales_channel_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return AgenticDiscoverySalesChannelConfigEntity::class;
    }
}
