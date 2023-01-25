<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class LocaleEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const LOCALE_WRITTEN_EVENT = 'locale.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const LOCALE_DELETED_EVENT = 'locale.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const LOCALE_LOADED_EVENT = 'locale.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const LOCALE_SEARCH_RESULT_LOADED_EVENT = 'locale.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const LOCALE_AGGREGATION_LOADED_EVENT = 'locale.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const LOCALE_ID_SEARCH_RESULT_LOADED_EVENT = 'locale.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const LOCALE_TRANSLATION_WRITTEN_EVENT = 'locale_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const LOCALE_TRANSLATION_DELETED_EVENT = 'locale_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const LOCALE_TRANSLATION_LOADED_EVENT = 'locale_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const LOCALE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'locale_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const LOCALE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'locale_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const LOCALE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'locale_translation.id.search.result.loaded';
}
