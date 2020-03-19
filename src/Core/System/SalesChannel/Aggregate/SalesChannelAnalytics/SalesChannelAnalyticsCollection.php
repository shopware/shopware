<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                             add(SalesChannelAnalyticsEntity $entity)
 * @method void                             set(string $key, SalesChannelAnalyticsEntity $entity)
 * @method SalesChannelAnalyticsEntity[]    getIterator()
 * @method SalesChannelAnalyticsEntity[]    getElements()
 * @method SalesChannelAnalyticsEntity|null get(string $key)
 * @method SalesChannelAnalyticsEntity|null first()
 * @method SalesChannelAnalyticsEntity|null last()
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
