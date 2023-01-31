<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class NewsletterEvents
{
    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent")
     */
    final public const NEWSLETTER_CONFIRM_EVENT = NewsletterConfirmEvent::class;

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const NEWSLETTER_RECIPIENT_WRITTEN_EVENT = 'newsletter_recipient.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const NEWSLETTER_RECIPIENT_DELETED_EVENT = 'newsletter_recipient.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const NEWSLETTER_RECIPIENT_LOADED_EVENT = 'newsletter_recipient.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const NEWSLETTER_RECIPIENT_SEARCH_RESULT_LOADED_EVENT = 'newsletter_recipient.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const NEWSLETTER_RECIPIENT_AGGREGATION_LOADED_EVENT = 'newsletter_recipient.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const NEWSLETTER_RECIPIENT_ID_SEARCH_RESULT_LOADED_EVENT = 'newsletter_recipient.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent")
     */
    final public const NEWSLETTER_REGISTER_EVENT = NewsletterRegisterEvent::class;

    /**
     * @Event("Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent")
     */
    final public const NEWSLETTER_UNSUBSCRIBE_EVENT = NewsletterUnsubscribeEvent::class;
}
