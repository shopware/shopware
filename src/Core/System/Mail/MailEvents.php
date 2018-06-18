<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail;

class MailEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const MAIL_WRITTEN_EVENT = 'mail.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const MAIL_DELETED_EVENT = 'mail.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const MAIL_LOADED_EVENT = 'mail.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const MAIL_SEARCH_RESULT_LOADED_EVENT = 'mail.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const MAIL_AGGREGATION_LOADED_EVENT = 'mail.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MAIL_ID_SEARCH_RESULT_LOADED_EVENT = 'mail.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const MAIL_ATTACHMENT_WRITTEN_EVENT = 'mail_attachment.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const MAIL_ATTACHMENT_DELETED_EVENT = 'mail_attachment.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const MAIL_ATTACHMENT_LOADED_EVENT = 'mail_attachment.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const MAIL_ATTACHMENT_SEARCH_RESULT_LOADED_EVENT = 'mail_attachment.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const MAIL_ATTACHMENT_AGGREGATION_LOADED_EVENT = 'mail_attachment.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MAIL_ATTACHMENT_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_attachment.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const MAIL_TRANSLATION_WRITTEN_EVENT = 'mail_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const MAIL_TRANSLATION_DELETED_EVENT = 'mail_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const MAIL_TRANSLATION_LOADED_EVENT = 'mail_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const MAIL_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'mail_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const MAIL_TRANSLATION_AGGREGATION_LOADED_EVENT = 'mail_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MAIL_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'mail_translation.id.search.result.loaded';
}