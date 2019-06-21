<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;

class NavigationEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent")
     */
    public const NAVIGATION_PAGE_LOADED_EVENT = NavigationPageLoadedEvent::NAME;
}
