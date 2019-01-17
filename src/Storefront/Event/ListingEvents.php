<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Listing\ListingPageLoadedEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Listing\PageCriteriaCreatedEvent;

class ListingEvents
{
    /**
     * Dispatched as soon as the search has been executed and the data is assigned to the view
     *
     * @Event("ListingPageLoadedEvent")
     */
    public const LOADED = ListingPageLoadedEvent::NAME;

    /**
     * Dispatched as soon as the search has been executed and the data is assigned to the view
     *
     * @Event("ListingPageLoadedEvent")
     */
    public const LISTING_PAGELET_LOADED = ListingPageletLoadedEvent::NAME;

    /**
     * Fired when a Criteria object is created for a product list in the storefront.
     *
     * @Event("PageCriteriaCreatedEvent")
     */
    public const CRITERIA_CREATED = PageCriteriaCreatedEvent::NAME;
}
