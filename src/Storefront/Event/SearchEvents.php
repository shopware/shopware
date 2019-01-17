<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Search\SearchPageRequestEvent;
use Shopware\Storefront\Pagelet\Search\SearchPageletRequestEvent;

class SearchEvents
{
    /**
     * Dispatched as soon as the search equest is send
     *
     * @Event("SearchPageletRequestEvent")
     */
    public const SEARCH_PAGELET_REQUEST = SearchPageletRequestEvent::NAME;

    /**
     * Dispatched as soon as the search equest is send
     *
     * @Event("SearchPageRequestEvent")
     */
    public const SEARCH_PAGE_REQUEST = SearchPageRequestEvent::NAME;
}
