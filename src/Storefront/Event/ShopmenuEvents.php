<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequestEvent;

class ShopmenuEvents
{
    /**
     * Fired when a ContentHome Page request comes in and transformed to the ContentHomePageRequest object
     *
     * @Event("ContentHomePageRequestEvent")
     */
    public const SHOPMENU_PAGELET_REQUEST = ShopmenuPageletRequestEvent::NAME;
}
