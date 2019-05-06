<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver;

class NewsletterReceiverEvents
{
    /**
     * @Event("Shopware\Core\Content\NewsletterReceiver\Event\NewsletterConfirmEvent")
     */
    public const NEWSLETTER_CONFIRM_EVENT = 'newsletter.confirm';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const NEWSLETTER_RECEIVER_WRITTEN_EVENT = 'newsletter_receiver.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const NEWSLETTER_RECEIVER_DELETED_EVENT = 'newsletter_receiver.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const NEWSLETTER_RECEIVER_LOADED_EVENT = 'newsletter_receiver.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const NEWSLETTER_RECEIVER_SEARCH_RESULT_LOADED_EVENT = 'newsletter_receiver.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const NEWSLETTER_RECEIVER_AGGREGATION_LOADED_EVENT = 'newsletter_receiver.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const NEWSLETTER_RECEIVER_ID_SEARCH_RESULT_LOADED_EVENT = 'newsletter_receiver.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Content\NewsletterReceiver\Event\NewsletterRegisterEvent")
     */
    public const NEWSLETTER_REGISTER_EVENT = 'newsletter.register';
}
