<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;

class ProductEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Product\ProductPageLoadedEvent")
     */
    public const PRODUCT_PAGE_LOADED_EVENT = ProductPageLoadedEvent::NAME;
}
