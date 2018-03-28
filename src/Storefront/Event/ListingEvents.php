<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

class ListingEvents
{
    /**
     * Dispatched as soon as the search has been executed and the data is assigned to the view
     *
     * @Event("Shopware\Storefront\Event\ListingPageLoadedEvent")
     */
    public const LISTING_PAGE_LOADED_EVENT = ListingPageLoadedEvent::NAME;

    /**
     * Fired when a Criteria object is created for a product list in the storefront.
     *
     * @Event("Shopware\Storefront\Event\PageCriteriaCreatedEvent")
     */
    public const PAGE_CRITERIA_CREATED_EVENT = PageCriteriaCreatedEvent::NAME;
}
