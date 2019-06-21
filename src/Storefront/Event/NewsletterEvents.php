<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterPageLoadedEvent;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoadedEvent;

class NewsletterEvents
{
    /**
     * @Event("Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterPageLoadedEvent")
     */
    public const NEWSLETTER_REGISTER_PAGE_LOADED_EVENT = NewsletterRegisterPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Newsletter\ConfirmSubscribe\NewsletterSubscribePageLoadedEvent")
     */
    public const NEWSLETTER_SUBSCRIBE_PAGE_LOADED_EVENT = NewsletterSubscribePageLoadedEvent::NAME;
}
