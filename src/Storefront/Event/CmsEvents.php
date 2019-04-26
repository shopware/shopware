<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Pagelet\Cms\CmsPageletLoadedEvent;

class CmsEvents
{
    /**
     * @Event("Shopware\Storefront\Pagelet\Cms\CmsPageletLoadedEvent")
     */
    public const CMS_PAGELET_LOADED_EVENT = CmsPageletLoadedEvent::NAME;
}
