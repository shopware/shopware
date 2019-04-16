<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Content\NewsletterReceiver\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\NewsletterReceiver\Event\NewsletterRegisterEvent;
use Shopware\Storefront\Page\Newsletter\Confirm\NewsletterConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Newsletter\ConfirmSubscribe\NewsletterConfirmSubscribePageLoadedEvent;
use Shopware\Storefront\Page\Newsletter\Error\NewsletterErrorPageLoadedEvent;
use Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterPageLoadedEvent;

class NewsletterEvents
{
    /**
     * @Event("Shopware\Core\Content\NewsletterReceiver\Event\NewsletterRegisterEvent")
     */
    public const NEWSLETTER_REGISTER_EVENT = NewsletterRegisterEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Core\Content\NewsletterReceiver\Event\NewsletterConfirmEvent")
     */
    public const NEWSLETTER_REGISTER_CONFIRM_EVENT = NewsletterConfirmEvent::EVENT_NAME;

    /**
     * @Event("Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterPageLoadedEvent")
     */
    public const NEWSLETTER_PAGE_LOADED_EVENT = NewsletterRegisterPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Newsletter\Confirm\NewsletterConfirmPageLoadedEvent")
     */
    public const NEWSLETTER_CONFIRM_PAGE_LOADED_EVENT = NewsletterConfirmPageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Newsletter\ConfirmSubscribe\NewsletterConfirmSubscribePageLoadedEvent")
     */
    public const NEWSLETTER_CONFIRM_SUBSCRIBE_PAGE_LOADED_EVENT = NewsletterConfirmSubscribePageLoadedEvent::NAME;

    /**
     * @Event("Shopware\Storefront\Page\Newsletter\Error\NewsletterErrorPageLoadedEvent")
     */
    public const NEWSLETTER_ERROR_PAGE_LOADED_EVENT = NewsletterErrorPageLoadedEvent::NAME;
}
