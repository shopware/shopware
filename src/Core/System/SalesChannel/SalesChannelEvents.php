<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

class SalesChannelEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const SALES_CHANNEL_WRITTEN = 'sales_channel.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const SALES_CHANNEL_DELETED = 'sales_channel.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const SALES_CHANNEL_LOADED = 'sales_channel.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_SEARCH_RESULT_LOADED = 'sales_channel.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const SALES_CHANNEL_AGGREGATION_RESULT_LOADED = 'sales_channel.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_ID_SEARCH_RESULT_LOADED = 'sales_channel.id.search.result.loaded';
}
