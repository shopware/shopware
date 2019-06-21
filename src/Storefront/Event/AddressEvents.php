<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoadedEvent;
use Shopware\Storefront\Page\Address\Listing\AddressListingPageLoadedEvent;

class AddressEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoadedEvent")
     */
    public const ADDRESS_DETAIL_PAGE_LOADED_EVENT = AddressDetailPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Address\Listing\AddressListingPageLoadedEvent")
     */
    public const ADDRESS_LISTING_PAGE_LOADED_EVENT = AddressListingPageLoadedEvent::NAME;
}
