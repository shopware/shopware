<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

class SalesChannelEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const SALES_CHANNEL_WRITTEN = 'sales_channel.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const SALES_CHANNEL_DELETED = 'sales_channel.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const SALES_CHANNEL_LOADED = 'sales_channel.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_SEARCH_RESULT_LOADED = 'sales_channel.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const SALES_CHANNEL_AGGREGATION_RESULT_LOADED = 'sales_channel.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_ID_SEARCH_RESULT_LOADED = 'sales_channel.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const SALES_CHANNEL_TRANSLATION_WRITTEN_EVENT = 'sales_channel_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const SALES_CHANNEL_TRANSLATION_DELETED_EVENT = 'sales_channel_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const SALES_CHANNEL_TRANSLATION_LOADED_EVENT = 'sales_channel_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'sales_channel_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const SALES_CHANNEL_TRANSLATION_AGGREGATION_LOADED_EVENT = 'sales_channel_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'sales_channel_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const SALES_CHANNEL_TYPE_WRITTEN = 'sales_channel_type.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const SALES_CHANNEL_TYPE_DELETED = 'sales_channel_type.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const SALES_CHANNEL_TYPE_LOADED = 'sales_channel_type.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_TYPE_SEARCH_RESULT_LOADED = 'sales_channel_type.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const SALES_CHANNEL_TYPE_AGGREGATION_RESULT_LOADED = 'sales_channel_type.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_TYPE_ID_SEARCH_RESULT_LOADED = 'sales_channel_type.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const SALES_CHANNEL_TYPE_TRANSLATION_WRITTEN_EVENT = 'sales_channel_type_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const SALES_CHANNEL_TYPE_TRANSLATION_DELETED_EVENT = 'sales_channel_type_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const SALES_CHANNEL_TYPE_TRANSLATION_LOADED_EVENT = 'sales_channel_type_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_TYPE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'sales_channel_type_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const SALES_CHANNEL_TYPE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'sales_channel_type_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SALES_CHANNEL_TYPE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'sales_channel_type_translation.id.search.result.loaded';
}
