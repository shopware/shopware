<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

class UnitEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const UNIT_WRITTEN_EVENT = 'unit.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const UNIT_DELETED_EVENT = 'unit.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const UNIT_LOADED_EVENT = 'unit.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const UNIT_SEARCH_RESULT_LOADED_EVENT = 'unit.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const UNIT_AGGREGATION_LOADED_EVENT = 'unit.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const UNIT_ID_SEARCH_RESULT_LOADED_EVENT = 'unit.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const UNIT_TRANSLATION_WRITTEN_EVENT = 'unit_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const UNIT_TRANSLATION_DELETED_EVENT = 'unit_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const UNIT_TRANSLATION_LOADED_EVENT = 'unit_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const UNIT_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'unit_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const UNIT_TRANSLATION_AGGREGATION_LOADED_EVENT = 'unit_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const UNIT_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'unit_translation.id.search.result.loaded';
}
