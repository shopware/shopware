<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

class LocaleEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const LOCALE_WRITTEN_EVENT = 'locale.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const LOCALE_DELETED_EVENT = 'locale.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const LOCALE_LOADED_EVENT = 'locale.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const LOCALE_SEARCH_RESULT_LOADED_EVENT = 'locale.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const LOCALE_AGGREGATION_LOADED_EVENT = 'locale.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const LOCALE_ID_SEARCH_RESULT_LOADED_EVENT = 'locale.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const LOCALE_TRANSLATION_WRITTEN_EVENT = 'locale_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const LOCALE_TRANSLATION_DELETED_EVENT = 'locale_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const LOCALE_TRANSLATION_LOADED_EVENT = 'locale_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const LOCALE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'locale_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const LOCALE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'locale_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const LOCALE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'locale_translation.id.search.result.loaded';
}