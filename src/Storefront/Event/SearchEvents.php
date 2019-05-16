<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Shopware\Storefront\Page\Suggest\SuggestPageLoadedEvent;

class SearchEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Search\SearchPageLoadedEvent")
     */
    public const SEARCH_PAGE_LOADED_EVENT = SearchPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Suggest\SuggestPageLoadedEvent")
     */
    public const SUGGEST_PAGE_LOADED_EVENT = SuggestPageLoadedEvent::NAME;
}
