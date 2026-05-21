<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @extends EntityCollection<UcpSalesChannelConfigEntity>
 *
 * @internal
 */
#[Package('framework')]
class UcpSalesChannelConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ucp_sales_channel_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return UcpSalesChannelConfigEntity::class;
    }
}
