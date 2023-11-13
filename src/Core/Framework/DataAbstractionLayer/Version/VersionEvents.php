<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class VersionEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const VERSION_WRITTEN_EVENT = 'version.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const VERSION_DELETED_EVENT = 'version.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const VERSION_LOADED_EVENT = 'version.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const VERSION_SEARCH_RESULT_LOADED_EVENT = 'version.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const VERSION_AGGREGATION_LOADED_EVENT = 'version.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const VERSION_ID_SEARCH_RESULT_LOADED_EVENT = 'version.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const VERSION_COMMIT_WRITTEN_EVENT = 'version_commit.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const VERSION_COMMIT_DELETED_EVENT = 'version_commit.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const VERSION_COMMIT_LOADED_EVENT = 'version_commit.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const VERSION_COMMIT_SEARCH_RESULT_LOADED_EVENT = 'version_commit.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const VERSION_COMMIT_AGGREGATION_LOADED_EVENT = 'version_commit.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const VERSION_COMMIT_ID_SEARCH_RESULT_LOADED_EVENT = 'version_commit.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const VERSION_COMMIT_DATA_WRITTEN_EVENT = 'version_commit_data.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const VERSION_COMMIT_DATA_DELETED_EVENT = 'version_commit_data.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const VERSION_COMMIT_DATA_LOADED_EVENT = 'version_commit_data.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const VERSION_COMMIT_DATA_SEARCH_RESULT_LOADED_EVENT = 'version_commit_data.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const VERSION_COMMIT_DATA_AGGREGATION_LOADED_EVENT = 'version_commit_data.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const VERSION_COMMIT_DATA_ID_SEARCH_RESULT_LOADED_EVENT = 'version_commit_data.id.search.result.loaded';
}
