<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;

class ProductEvents
{
    /**
     * Dispatched as soon as the product page has been loaded
     *
     * @Event("ProductProductPageLoadedEvent")
     */
    public const PRODUCTDETAIL_PAGE_LOADED = ProductPageLoadedEvent::NAME;
}
