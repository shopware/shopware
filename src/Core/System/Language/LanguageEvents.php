<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

class LanguageEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const LANGUAGE_WRITTEN_EVENT = 'language.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const LANGUAGE_DELETED_EVENT = 'language.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const LANGUAGE_LOADED_EVENT = 'language.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const LANGUAGE_SEARCH_RESULT_LOADED_EVENT = 'language.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const LANGUAGE_AGGREGATION_LOADED_EVENT = 'language.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const LANGUAGE_ID_SEARCH_RESULT_LOADED_EVENT = 'language.id.search.result.loaded';
}
