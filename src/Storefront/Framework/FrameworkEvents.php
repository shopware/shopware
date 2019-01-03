<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework;

use Shopware\Storefront\Framework\Event\PageletRequestEvent;
use Shopware\Storefront\Framework\Event\PageRequestEvent;

class FrameworkEvents
{
    /**
     * Fired when a Page request comes in and transformed to the PageRequest object
     *
     * @Event("Shopware\Storefront\Framework\Event\PageRequestEvent")
     */
    public const REQUEST = PageRequestEvent::NAME;

    /**
     * Fired when a Pagelet request comes in and transformed to the PageletRequest object
     *
     * @Event("Shopware\Storefront\Framework\Event\PageletRequestEvent")
     */
    public const PAGELET_REQUEST = PageletRequestEvent::NAME;
}
