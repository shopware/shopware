<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Suggest\SuggestPageLoadedEvent;

class SuggestEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Suggest\SuggestPageLoadedEvent")
     */
    public const SUGGEST_PAGE_LOADED_EVENT = SuggestPageLoadedEvent::NAME;
}
