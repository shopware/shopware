<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class IntegrationEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const INTEGRATION_WRITTEN_EVENT = 'integration.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const INTEGRATION_DELETED_EVENT = 'integration.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const INTEGRATION_LOADED_EVENT = 'integration.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const INTEGRATION_SEARCH_RESULT_LOADED_EVENT = 'integration.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const INTEGRATION_AGGREGATION_LOADED_EVENT = 'integration.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const INTEGRATION_ID_SEARCH_RESULT_LOADED_EVENT = 'integration.id.search.result.loaded';
}
