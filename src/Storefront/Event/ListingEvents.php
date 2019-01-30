<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Listing\ListingPageLoadedEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoadedEvent;

class ListingEvents
{
    /**
     * @Event("Shopware\Storefront\Pagelet\Listing\ListingPageletLoadedEvent")
     */
    public const LISTING_PAGELET_LOADED_EVENT = ListingPageletLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Listing\ListingPageLoadedEvent")
     */
    public const LISTING_PAGE_LOADED_EVENT = ListingPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent")
     */
    public const LISTING_PAGELET_CRITERIA_CREATED_EVENT = ListingPageletCriteriaCreatedEvent::NAME;
}
