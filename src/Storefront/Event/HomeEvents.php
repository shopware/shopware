<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Home\HomePageLoadedEvent;

class HomeEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Home\HomePageLoadedEvent")
     */
    public const HOME_PAGE_LOADED_EVENT = HomePageLoadedEvent::NAME;
}
