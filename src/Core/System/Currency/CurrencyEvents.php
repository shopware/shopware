<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

class CurrencyEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CURRENCY_WRITTEN_EVENT = 'currency.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CURRENCY_DELETED_EVENT = 'currency.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CURRENCY_LOADED_EVENT = 'currency.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CURRENCY_SEARCH_RESULT_LOADED_EVENT = 'currency.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CURRENCY_AGGREGATION_LOADED_EVENT = 'currency.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CURRENCY_ID_SEARCH_RESULT_LOADED_EVENT = 'currency.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CURRENCY_TRANSLATION_WRITTEN_EVENT = 'currency_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CURRENCY_TRANSLATION_DELETED_EVENT = 'currency_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CURRENCY_TRANSLATION_LOADED_EVENT = 'currency_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CURRENCY_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'currency_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CURRENCY_TRANSLATION_AGGREGATION_LOADED_EVENT = 'currency_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CURRENCY_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'currency_translation.id.search.result.loaded';
}
