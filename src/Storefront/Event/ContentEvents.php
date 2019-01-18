<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;

class ContentEvents
{
    /**
     * Dispatched each time a page is loaded with a header
     *
     * @Event("HeaderPageletLoadedEvent")
     */
    public const HEADER_PAGELET_LOADED = HeaderPageletLoadedEvent::NAME;
}
