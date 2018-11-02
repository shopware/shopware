<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

class PluginEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const PLUGIN_WRITTEN_EVENT = 'plugin.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const PLUGIN_DELETED_EVENT = 'plugin.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const PLUGIN_LOADED_EVENT = 'plugin.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const PLUGIN_SEARCH_RESULT_LOADED_EVENT = 'plugin.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const PLUGIN_AGGREGATION_LOADED_EVENT = 'plugin.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PLUGIN_ID_SEARCH_RESULT_LOADED_EVENT = 'plugin.id.search.result.loaded';
}
