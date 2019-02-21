<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

class AttributeEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const ATTRIBUTE_WRITTEN_EVENT = 'attribute.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const ATTRIBUTE_DELETED_EVENT = 'attribute.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const ATTRIBUTE_LOADED_EVENT = 'attribute.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const ATTRIBUTE_SEARCH_RESULT_LOADED_EVENT = 'attribute.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const ATTRIBUTE_AGGREGATION_LOADED_EVENT = 'attribute.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const ATTRIBUTE_ID_SEARCH_RESULT_LOADED_EVENT = 'attribute.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const ATTRIBUTE_SET_WRITTEN_EVENT = 'attribute_set.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const ATTRIBUTE_SET_DELETED_EVENT = 'attribute_set.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const ATTRIBUTE_SET_LOADED_EVENT = 'attribute_set.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const ATTRIBUTE_SET_SEARCH_RESULT_LOADED_EVENT = 'attribute_set.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const ATTRIBUTE_SET_AGGREGATION_LOADED_EVENT = 'attribute_set.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const ATTRIBUTE_SET_ID_SEARCH_RESULT_LOADED_EVENT = 'attribute_set.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const ATTRIBUTE_SET_RELATION_WRITTEN_EVENT = 'attribute_set_relation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const ATTRIBUTE_SET_RELATION_DELETED_EVENT = 'attribute_set_relation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const ATTRIBUTE_SET_RELATION_LOADED_EVENT = 'attribute_set_relation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const ATTRIBUTE_SET_RELATION_SEARCH_RESULT_LOADED_EVENT = 'attribute_set_relation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const ATTRIBUTE_SET_RELATION_AGGREGATION_LOADED_EVENT = 'attribute_set_relation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const ATTRIBUTE_SET_RELATION_ID_SEARCH_RESULT_LOADED_EVENT = 'attribute_set_relation.id.search.result.loaded';
}
