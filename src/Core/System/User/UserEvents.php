<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

class UserEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const USER_WRITTEN_EVENT = 'user.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const USER_DELETED_EVENT = 'user.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const USER_LOADED_EVENT = 'user.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const USER_SEARCH_RESULT_LOADED_EVENT = 'user.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const USER_AGGREGATION_LOADED_EVENT = 'user.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const USER_ID_SEARCH_RESULT_LOADED_EVENT = 'user.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const USER_ACCESS_KEY_WRITTEN_EVENT = 'user_access_key.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const USER_ACCESS_KEY_DELETED_EVENT = 'user_access_key.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const USER_ACCESS_KEY_LOADED_EVENT = 'user_access_key.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const USER_ACCESS_KEY_SEARCH_RESULT_LOADED_EVENT = 'user_access_key.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const USER_ACCESS_KEY_AGGREGATION_LOADED_EVENT = 'user_access_key.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const USER_ACCESS_KEY_ID_SEARCH_RESULT_LOADED_EVENT = 'user_access_key.id.search.result.loaded';
}
