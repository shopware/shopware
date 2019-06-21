<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;

class SearchEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Search\SearchPageLoadedEvent")
     */
    public const SEARCH_PAGE_LOADED_EVENT = SearchPageLoadedEvent::NAME;
}
