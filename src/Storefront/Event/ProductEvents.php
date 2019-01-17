<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\ProductDetail\ProductDetailPageLoadedEvent;

class ProductEvents
{
    /**
     * Dispatched as soon as the productpage has been loaded
     *
     * @Event("ProductProductPageLoadedEvent")
     */
    public const PRODUCTDETAIL_PAGE_LOADED = ProductDetailPageLoadedEvent::NAME;
}
