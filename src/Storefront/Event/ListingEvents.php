<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Listing\ListingPageLoadedEvent;
use Shopware\Storefront\Page\Listing\ListingPageRequestEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequestEvent;
use Shopware\Storefront\Pagelet\Listing\PageCriteriaCreatedEvent;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequestEvent;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletRequestEvent;

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

    /**
     * Fired when a listing page request comes in and transformed to the ListingRequest object
     *
     * @Event("ListingPageRequestEvent")
     */
    public const LISTING_PAGE_REQUEST = ListingPageRequestEvent::NAME;

    /**
     * Fired when a Navigation pagelet request comes in and transformed to the NavigationPageletRequest object
     *
     * @Event("NavigationPageletRequestEvent")
     */
    public const NAVIGATION_REQUEST = NavigationPageletRequestEvent::NAME;

    /**
     * Fired when a Navigation sidebar pagelet request comes in and transformed to the NavigationSidebarPageletRequest object
     *
     * @Event("NavigationSidebarPageletRequestEvent")
     */
    public const NAVIGATIONSIDEBAR_PAGELET_REQUEST = NavigationSidebarPageletRequestEvent::NAME;

    /**
     * Fired when a Listing pagelet request comes in and transformed to the ListingPageletRequest object
     *
     * @Event("ListingPageletRequestEvent")
     */
    public const LISTING_PAGELET_REQUEST = ListingPageletRequestEvent::NAME;
}
