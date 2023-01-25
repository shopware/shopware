<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class PluginEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const PLUGIN_WRITTEN_EVENT = 'plugin.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const PLUGIN_DELETED_EVENT = 'plugin.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const PLUGIN_LOADED_EVENT = 'plugin.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const PLUGIN_SEARCH_RESULT_LOADED_EVENT = 'plugin.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const PLUGIN_AGGREGATION_LOADED_EVENT = 'plugin.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const PLUGIN_ID_SEARCH_RESULT_LOADED_EVENT = 'plugin.id.search.result.loaded';
}
