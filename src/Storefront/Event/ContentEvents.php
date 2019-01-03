<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Framework\Event\PageletRequestEvent;
use Shopware\Storefront\Page\Home\IndexPageRequestEvent;

class ContentEvents
{
    /**
     * Fired when a Index Page request comes in and transformed to the IndexPageRequest object
     *
     * @Event("IndexPageRequestEvent")
     */
    public const INDEX_PAGE_REQUEST = IndexPageRequestEvent::NAME;

    /**
     * Fired when a Pagelet request comes in and transformed to the PageletRequest object
     *
     * @Event("PageletRequestEvent")
     */
    public const PAGELET_REQUEST = PageletRequestEvent::NAME;
}
