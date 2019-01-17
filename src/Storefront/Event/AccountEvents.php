<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\AccountAddress\AccountAddressPageLoadedEvent;

class AccountEvents
{
    /**
     * Dispatched as soon as the productpage has been loaded
     *
     * @Event("AccountPageLoadedEvent")
     */
    public const LOADED = AccountAddressPageLoadedEvent::NAME;
}
