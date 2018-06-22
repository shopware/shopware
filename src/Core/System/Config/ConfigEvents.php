<?php declare(strict_types=1);

namespace Shopware\Core\System\Config;

class ConfigEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CONFIG_FORM_WRITTEN_EVENT = 'config_form.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CONFIG_FORM_DELETED_EVENT = 'config_form.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CONFIG_FORM_LOADED_EVENT = 'config_form.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIG_FORM_SEARCH_RESULT_LOADED_EVENT = 'config_form.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIG_FORM_AGGREGATION_LOADED_EVENT = 'config_form.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIG_FORM_ID_SEARCH_RESULT_LOADED_EVENT = 'config_form.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CONFIG_FORM_FIELD_WRITTEN_EVENT = 'config_form_field.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CONFIG_FORM_FIELD_DELETED_EVENT = 'config_form_field.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_LOADED_EVENT = 'config_form_field.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_SEARCH_RESULT_LOADED_EVENT = 'config_form_field.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_AGGREGATION_LOADED_EVENT = 'config_form_field.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_ID_SEARCH_RESULT_LOADED_EVENT = 'config_form_field.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CONFIG_FORM_FIELD_TRANSLATION_WRITTEN_EVENT = 'config_form_field_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CONFIG_FORM_FIELD_TRANSLATION_DELETED_EVENT = 'config_form_field_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_TRANSLATION_LOADED_EVENT = 'config_form_field_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'config_form_field_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_TRANSLATION_AGGREGATION_LOADED_EVENT = 'config_form_field_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'config_form_field_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CONFIG_FORM_FIELD_VALUE_WRITTEN_EVENT = 'config_form_field_value.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CONFIG_FORM_FIELD_VALUE_DELETED_EVENT = 'config_form_field_value.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_VALUE_LOADED_EVENT = 'config_form_field_value.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_VALUE_SEARCH_RESULT_LOADED_EVENT = 'config_form_field_value.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_VALUE_AGGREGATION_LOADED_EVENT = 'config_form_field_value.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIG_FORM_FIELD_VALUE_ID_SEARCH_RESULT_LOADED_EVENT = 'config_form_field_value.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CONFIG_FORM_TRANSLATION_WRITTEN_EVENT = 'config_form_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CONFIG_FORM_TRANSLATION_DELETED_EVENT = 'config_form_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CONFIG_FORM_TRANSLATION_LOADED_EVENT = 'config_form_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CONFIG_FORM_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'config_form_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CONFIG_FORM_TRANSLATION_AGGREGATION_LOADED_EVENT = 'config_form_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CONFIG_FORM_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'config_form_translation.id.search.result.loaded';
}
