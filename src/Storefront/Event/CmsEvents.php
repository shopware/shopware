<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Pagelet\Cms\CmsPageletLoadedEvent;

/**
 * @copyright 2019 dasistweb GmbH (https://www.dasistweb.de)
 */
class CmsEvents
{
    /**
     * @Event("Shopware\Storefront\Pagelet\Cms\CmsPageletLoadedEvent")
     */
    public const CMS_PAGELET_LOADED_EVENT = CmsPageletLoadedEvent::NAME;
}
