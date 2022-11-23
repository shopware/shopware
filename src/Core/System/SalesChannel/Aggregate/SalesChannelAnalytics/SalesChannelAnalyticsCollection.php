<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package sales-channel
 *
 * @extends EntityCollection<SalesChannelAnalyticsEntity>
 */
class SalesChannelAnalyticsCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_analytics_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelAnalyticsEntity::class;
    }
}
