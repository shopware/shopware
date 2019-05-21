<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterPageLoadedEvent;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoadedEvent;

class NewsletterEvents
{
    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent")
     */
    public const NEWSLETTER_REGISTER_EVENT = \Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent")
     */
    public const NEWSLETTER_REGISTER_CONFIRM_EVENT = NewsletterConfirmEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterPageLoadedEvent")
     */
    public const NEWSLETTER_PAGE_LOADED_EVENT = NewsletterRegisterPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Newsletter\ConfirmSubscribe\NewsletterSubscribePageLoadedEvent")
     */
    public const NEWSLETTER_CONFIRM_SUBSCRIBE_PAGE_LOADED_EVENT = NewsletterSubscribePageLoadedEvent::NAME;
}
