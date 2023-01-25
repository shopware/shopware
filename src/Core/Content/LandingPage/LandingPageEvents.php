<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage;

use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class LandingPageEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const LANDING_PAGE_WRITTEN_EVENT = 'landing_page.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const LANDING_PAGE_DELETED_EVENT = 'landing_page.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const LANDING_PAGE_LOADED_EVENT = 'landing_page.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const LANDING_PAGE_SEARCH_RESULT_LOADED_EVENT = 'landing_page.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const LANDING_PAGE_AGGREGATION_LOADED_EVENT = 'landing_page.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const LANDING_PAGE_ID_SEARCH_RESULT_LOADED_EVENT = 'landing_page.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const LANDING_PAGE_TRANSLATION_WRITTEN_EVENT = 'landing_page_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const LANDING_PAGE_TRANSLATION_DELETED_EVENT = 'landing_page_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const LANDING_PAGE_TRANSLATION_LOADED_EVENT = 'landing_page_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const LANDING_PAGE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'landing_page_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const LANDING_PAGE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'landing_page_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const LANDING_PAGE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'landing_page_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent")
     */
    final public const LANDING_PAGE_INDEXER_EVENT = LandingPageIndexerEvent::class;
}
