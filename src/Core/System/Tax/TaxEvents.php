<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

class TaxEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const TAX_WRITTEN_EVENT = 'tax.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const TAX_DELETED_EVENT = 'tax.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const TAX_LOADED_EVENT = 'tax.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const TAX_SEARCH_RESULT_LOADED_EVENT = 'tax.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const TAX_AGGREGATION_LOADED_EVENT = 'tax.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const TAX_ID_SEARCH_RESULT_LOADED_EVENT = 'tax.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const TAX_AREA_RULE_WRITTEN_EVENT = 'tax_area_rule.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const TAX_AREA_RULE_DELETED_EVENT = 'tax_area_rule.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const TAX_AREA_RULE_LOADED_EVENT = 'tax_area_rule.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const TAX_AREA_RULE_SEARCH_RESULT_LOADED_EVENT = 'tax_area_rule.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const TAX_AREA_RULE_AGGREGATION_LOADED_EVENT = 'tax_area_rule.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const TAX_AREA_RULE_ID_SEARCH_RESULT_LOADED_EVENT = 'tax_area_rule.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const TAX_AREA_RULE_TRANSLATION_WRITTEN_EVENT = 'tax_area_rule_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const TAX_AREA_RULE_TRANSLATION_DELETED_EVENT = 'tax_area_rule_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const TAX_AREA_RULE_TRANSLATION_LOADED_EVENT = 'tax_area_rule_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const TAX_AREA_RULE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'tax_area_rule_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const TAX_AREA_RULE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'tax_area_rule_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const TAX_AREA_RULE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'tax_area_rule_translation.id.search.result.loaded';
}
