<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation;

class NavigationEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const NAVIGATION_WRITTEN_EVENT = 'navigation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const NAVIGATION_DELETED_EVENT = 'navigation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const NAVIGATION_LOADED_EVENT = 'navigation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const NAVIGATION_SEARCH_RESULT_LOADED_EVENT = 'navigation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const NAVIGATION_AGGREGATION_LOADED_EVENT = 'navigation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const NAVIGATION_ID_SEARCH_RESULT_LOADED_EVENT = 'navigation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const NAVIGATION_TRANSLATION_WRITTEN_EVENT = 'navigation_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const NAVIGATION_TRANSLATION_DELETED_EVENT = 'navigation_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const NAVIGATION_TRANSLATION_LOADED_EVENT = 'navigation_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const NAVIGATION_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'navigation_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const NAVIGATION_TRANSLATION_AGGREGATION_LOADED_EVENT = 'navigation_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const NAVIGATION_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'navigation_translation.id.search.result.loaded';
}
