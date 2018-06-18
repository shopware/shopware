<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

class UserEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const USER_WRITTEN_EVENT = 'user.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const USER_DELETED_EVENT = 'user.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const USER_LOADED_EVENT = 'user.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const USER_SEARCH_RESULT_LOADED_EVENT = 'user.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const USER_AGGREGATION_LOADED_EVENT = 'user.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const USER_ID_SEARCH_RESULT_LOADED_EVENT = 'user.id.search.result.loaded';
}