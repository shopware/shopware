<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

class ConfigurationEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CONFIGURATION_GROUP_WRITTEN_EVENT = 'configuration_group.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CONFIGURATION_GROUP_DELETED_EVENT = 'configuration_group.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CONFIGURATION_GROUP_LOADED_EVENT = 'configuration_group.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_SEARCH_RESULT_LOADED_EVENT = 'configuration_group.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_AGGREGATION_LOADED_EVENT = 'configuration_group.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_ID_SEARCH_RESULT_LOADED_EVENT = 'configuration_group.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_WRITTEN_EVENT = 'configuration_group_option.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_DELETED_EVENT = 'configuration_group_option.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_LOADED_EVENT = 'configuration_group_option.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_SEARCH_RESULT_LOADED_EVENT = 'configuration_group_option.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_AGGREGATION_LOADED_EVENT = 'configuration_group_option.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_ID_SEARCH_RESULT_LOADED_EVENT = 'configuration_group_option.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_TRANSLATION_WRITTEN_EVENT = 'configuration_group_option_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_TRANSLATION_DELETED_EVENT = 'configuration_group_option_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_TRANSLATION_LOADED_EVENT = 'configuration_group_option_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'configuration_group_option_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_TRANSLATION_AGGREGATION_LOADED_EVENT = 'configuration_group_option_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_OPTION_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'configuration_group_option_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CONFIGURATION_GROUP_TRANSLATION_WRITTEN_EVENT = 'configuration_group_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CONFIGURATION_GROUP_TRANSLATION_DELETED_EVENT = 'configuration_group_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CONFIGURATION_GROUP_TRANSLATION_LOADED_EVENT = 'configuration_group_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'configuration_group_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_TRANSLATION_AGGREGATION_LOADED_EVENT = 'configuration_group_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIGURATION_GROUP_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'configuration_group_translation.id.search.result.loaded';
}
