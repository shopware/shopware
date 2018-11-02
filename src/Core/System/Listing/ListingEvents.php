<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

class ListingEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const LISTING_FACET_WRITTEN_EVENT = 'listing_facet.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const LISTING_FACET_DELETED_EVENT = 'listing_facet.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const LISTING_FACET_LOADED_EVENT = 'listing_facet.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const LISTING_FACET_SEARCH_RESULT_LOADED_EVENT = 'listing_facet.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const LISTING_FACET_AGGREGATION_LOADED_EVENT = 'listing_facet.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const LISTING_FACET_ID_SEARCH_RESULT_LOADED_EVENT = 'listing_facet.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const LISTING_FACET_TRANSLATION_WRITTEN_EVENT = 'listing_facet_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const LISTING_FACET_TRANSLATION_DELETED_EVENT = 'listing_facet_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const LISTING_FACET_TRANSLATION_LOADED_EVENT = 'listing_facet_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const LISTING_FACET_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'listing_facet_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const LISTING_FACET_TRANSLATION_AGGREGATION_LOADED_EVENT = 'listing_facet_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const LISTING_FACET_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'listing_facet_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const LISTING_SORTING_WRITTEN_EVENT = 'listing_sorting.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const LISTING_SORTING_DELETED_EVENT = 'listing_sorting.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const LISTING_SORTING_LOADED_EVENT = 'listing_sorting.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const LISTING_SORTING_SEARCH_RESULT_LOADED_EVENT = 'listing_sorting.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const LISTING_SORTING_AGGREGATION_LOADED_EVENT = 'listing_sorting.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const LISTING_SORTING_ID_SEARCH_RESULT_LOADED_EVENT = 'listing_sorting.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const LISTING_SORTING_TRANSLATION_WRITTEN_EVENT = 'listing_sorting_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const LISTING_SORTING_TRANSLATION_DELETED_EVENT = 'listing_sorting_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const LISTING_SORTING_TRANSLATION_LOADED_EVENT = 'listing_sorting_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const LISTING_SORTING_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'listing_sorting_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const LISTING_SORTING_TRANSLATION_AGGREGATION_LOADED_EVENT = 'listing_sorting_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const LISTING_SORTING_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'listing_sorting_translation.id.search.result.loaded';
}
