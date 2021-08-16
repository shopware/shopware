<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;

class NewsletterEvents
{
    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent")
     */
    public const NEWSLETTER_CONFIRM_EVENT = NewsletterConfirmEvent::class;

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const NEWSLETTER_RECIPIENT_WRITTEN_EVENT = 'newsletter_recipient.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const NEWSLETTER_RECIPIENT_DELETED_EVENT = 'newsletter_recipient.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const NEWSLETTER_RECIPIENT_LOADED_EVENT = 'newsletter_recipient.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const NEWSLETTER_RECIPIENT_SEARCH_RESULT_LOADED_EVENT = 'newsletter_recipient.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const NEWSLETTER_RECIPIENT_AGGREGATION_LOADED_EVENT = 'newsletter_recipient.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const NEWSLETTER_RECIPIENT_ID_SEARCH_RESULT_LOADED_EVENT = 'newsletter_recipient.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent")
     */
    public const NEWSLETTER_REGISTER_EVENT = NewsletterRegisterEvent::class;

    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterUpdateEvent")
     *
     * @deprecated tag:v6.5.0 will be removed as it was not thrown
     */
    public const NEWSLETTER_UPDATE_EVENT = 'newsletter.update';

    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent")
     */
    public const NEWSLETTER_UNSUBSCRIBE_EVENT = NewsletterUnsubscribeEvent::class;
}
