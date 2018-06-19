<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

class ProductEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_WRITTEN_EVENT = 'product.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_DELETED_EVENT = 'product.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_LOADED_EVENT = 'product.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_SEARCH_RESULT_LOADED_EVENT = 'product.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_AGGREGATION_LOADED_EVENT = 'product.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_ID_SEARCH_RESULT_LOADED_EVENT = 'product.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_CATEGORY_WRITTEN_EVENT = 'product_category.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_CATEGORY_DELETED_EVENT = 'product_category.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_CATEGORY_LOADED_EVENT = 'product_category.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'product_category.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_CATEGORY_AGGREGATION_LOADED_EVENT = 'product_category.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'product_category.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_CONFIGURATOR_WRITTEN_EVENT = 'product_configurator.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_CONFIGURATOR_DELETED_EVENT = 'product_configurator.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_CONFIGURATOR_LOADED_EVENT = 'product_configurator.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_CONFIGURATOR_SEARCH_RESULT_LOADED_EVENT = 'product_configurator.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_CONFIGURATOR_AGGREGATION_LOADED_EVENT = 'product_configurator.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_CONFIGURATOR_ID_SEARCH_RESULT_LOADED_EVENT = 'product_configurator.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_PRICE_RULE_WRITTEN_EVENT = 'product_price_rule.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_PRICE_RULE_DELETED_EVENT = 'product_price_rule.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_PRICE_RULE_LOADED_EVENT = 'product_price_rule.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_PRICE_RULE_SEARCH_RESULT_LOADED_EVENT = 'product_price_rule.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_PRICE_RULE_AGGREGATION_LOADED_EVENT = 'product_price_rule.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_PRICE_RULE_ID_SEARCH_RESULT_LOADED_EVENT = 'product_price_rule.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_DATASHEET_WRITTEN_EVENT = 'product_datasheet.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_DATASHEET_DELETED_EVENT = 'product_datasheet.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_DATASHEET_LOADED_EVENT = 'product_datasheet.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_DATASHEET_SEARCH_RESULT_LOADED_EVENT = 'product_datasheet.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_DATASHEET_AGGREGATION_LOADED_EVENT = 'product_datasheet.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_DATASHEET_ID_SEARCH_RESULT_LOADED_EVENT = 'product_datasheet.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_MANUFACTURER_WRITTEN_EVENT = 'product_manufacturer.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_MANUFACTURER_DELETED_EVENT = 'product_manufacturer.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_MANUFACTURER_LOADED_EVENT = 'product_manufacturer.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_MANUFACTURER_SEARCH_RESULT_LOADED_EVENT = 'product_manufacturer.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_MANUFACTURER_AGGREGATION_LOADED_EVENT = 'product_manufacturer.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_MANUFACTURER_ID_SEARCH_RESULT_LOADED_EVENT = 'product_manufacturer.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_MANUFACTURER_TRANSLATION_WRITTEN_EVENT = 'product_manufacturer_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_MANUFACTURER_TRANSLATION_DELETED_EVENT = 'product_manufacturer_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_MANUFACTURER_TRANSLATION_LOADED_EVENT = 'product_manufacturer_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_MANUFACTURER_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'product_manufacturer_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_MANUFACTURER_TRANSLATION_AGGREGATION_LOADED_EVENT = 'product_manufacturer_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_MANUFACTURER_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'product_manufacturer_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_MEDIA_WRITTEN_EVENT = 'product_media.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_MEDIA_DELETED_EVENT = 'product_media.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_MEDIA_LOADED_EVENT = 'product_media.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_MEDIA_SEARCH_RESULT_LOADED_EVENT = 'product_media.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_MEDIA_AGGREGATION_LOADED_EVENT = 'product_media.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_MEDIA_ID_SEARCH_RESULT_LOADED_EVENT = 'product_media.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_SEARCH_KEYWORD_WRITTEN_EVENT = 'product_search_keyword.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_SEARCH_KEYWORD_DELETED_EVENT = 'product_search_keyword.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_SEARCH_KEYWORD_LOADED_EVENT = 'product_search_keyword.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_SEARCH_KEYWORD_SEARCH_RESULT_LOADED_EVENT = 'product_search_keyword.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_SEARCH_KEYWORD_AGGREGATION_LOADED_EVENT = 'product_search_keyword.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_SEARCH_KEYWORD_ID_SEARCH_RESULT_LOADED_EVENT = 'product_search_keyword.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_SEO_CATEGORY_WRITTEN_EVENT = 'product_seo_category.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_SEO_CATEGORY_DELETED_EVENT = 'product_seo_category.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_SEO_CATEGORY_LOADED_EVENT = 'product_seo_category.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_SEO_CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'product_seo_category.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_SEO_CATEGORY_AGGREGATION_LOADED_EVENT = 'product_seo_category.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_SEO_CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'product_seo_category.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_SERVICE_WRITTEN_EVENT = 'product_service.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_SERVICE_DELETED_EVENT = 'product_service.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_SERVICE_LOADED_EVENT = 'product_service.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_SERVICE_SEARCH_RESULT_LOADED_EVENT = 'product_service.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_SERVICE_AGGREGATION_LOADED_EVENT = 'product_service.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_SERVICE_ID_SEARCH_RESULT_LOADED_EVENT = 'product_service.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_STREAM_WRITTEN_EVENT = 'product_stream.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_STREAM_DELETED_EVENT = 'product_stream.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_STREAM_LOADED_EVENT = 'product_stream.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_STREAM_SEARCH_RESULT_LOADED_EVENT = 'product_stream.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_STREAM_AGGREGATION_LOADED_EVENT = 'product_stream.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_STREAM_ID_SEARCH_RESULT_LOADED_EVENT = 'product_stream.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_STREAM_ASSIGNMENT_WRITTEN_EVENT = 'product_stream_assignment.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_STREAM_ASSIGNMENT_DELETED_EVENT = 'product_stream_assignment.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_STREAM_ASSIGNMENT_LOADED_EVENT = 'product_stream_assignment.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_STREAM_ASSIGNMENT_SEARCH_RESULT_LOADED_EVENT = 'product_stream_assignment.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_STREAM_ASSIGNMENT_AGGREGATION_LOADED_EVENT = 'product_stream_assignment.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_STREAM_ASSIGNMENT_ID_SEARCH_RESULT_LOADED_EVENT = 'product_stream_assignment.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_STREAM_TAB_WRITTEN_EVENT = 'product_stream_tab.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_STREAM_TAB_DELETED_EVENT = 'product_stream_tab.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_STREAM_TAB_LOADED_EVENT = 'product_stream_tab.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_STREAM_TAB_SEARCH_RESULT_LOADED_EVENT = 'product_stream_tab.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_STREAM_TAB_AGGREGATION_LOADED_EVENT = 'product_stream_tab.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_STREAM_TAB_ID_SEARCH_RESULT_LOADED_EVENT = 'product_stream_tab.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_TRANSLATION_WRITTEN_EVENT = 'product_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_TRANSLATION_DELETED_EVENT = 'product_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_TRANSLATION_LOADED_EVENT = 'product_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'product_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_TRANSLATION_AGGREGATION_LOADED_EVENT = 'product_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'product_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_VARIATION_WRITTEN_EVENT = 'product_variation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_VARIATION_DELETED_EVENT = 'product_variation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_VARIATION_LOADED_EVENT = 'product_variation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_VARIATION_SEARCH_RESULT_LOADED_EVENT = 'product_variation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_VARIATION_AGGREGATION_LOADED_EVENT = 'product_variation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_VARIATION_ID_SEARCH_RESULT_LOADED_EVENT = 'product_variation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const PRODUCT_CATEGORY_TREE_WRITTEN_EVENT = 'product_category_tree.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const PRODUCT_CATEGORY_TREE_DELETED_EVENT = 'product_category_tree.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const PRODUCT_CATEGORY_TREE_LOADED_EVENT = 'product_category_tree.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const PRODUCT_CATEGORY_TREE_SEARCH_RESULT_LOADED_EVENT = 'product_category_tree.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const PRODUCT_CATEGORY_TREE_AGGREGATION_LOADED_EVENT = 'product_category_tree.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PRODUCT_CATEGORY_TREE_ID_SEARCH_RESULT_LOADED_EVENT = 'product_category_tree.id.search.result.loaded';
}
