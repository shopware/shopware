<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductEvents
{
    final public const PRODUCT_LISTING_CRITERIA = ProductListingCriteriaEvent::class;

    final public const PRODUCT_SUGGEST_CRITERIA = ProductSuggestCriteriaEvent::class;

    final public const PRODUCT_SEARCH_CRITERIA = ProductSearchCriteriaEvent::class;

    final public const PRODUCT_LISTING_RESULT = ProductListingResultEvent::class;

    final public const PRODUCT_SUGGEST_RESULT = ProductSuggestResultEvent::class;

    final public const PRODUCT_SEARCH_RESULT = ProductSearchResultEvent::class;

    final public const PRODUCT_INDEXER_EVENT = ProductIndexerEvent::class;

    final public const PRODUCT_WRITTEN_EVENT = 'product.written';

    final public const PRODUCT_DELETED_EVENT = 'product.deleted';

    final public const PRODUCT_LOADED_EVENT = 'product.loaded';

    final public const PRODUCT_SEARCH_RESULT_LOADED_EVENT = 'product.search.result.loaded';

    final public const PRODUCT_AGGREGATION_LOADED_EVENT = 'product.aggregation.result.loaded';

    final public const PRODUCT_ID_SEARCH_RESULT_LOADED_EVENT = 'product.id.search.result.loaded';

    final public const PRODUCT_CATEGORY_WRITTEN_EVENT = 'product_category.written';

    final public const PRODUCT_CATEGORY_DELETED_EVENT = 'product_category.deleted';

    final public const PRODUCT_CATEGORY_LOADED_EVENT = 'product_category.loaded';

    final public const PRODUCT_CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'product_category.search.result.loaded';

    final public const PRODUCT_CATEGORY_AGGREGATION_LOADED_EVENT = 'product_category.aggregation.result.loaded';

    final public const PRODUCT_CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'product_category.id.search.result.loaded';

    final public const PRODUCT_CONFIGURATOR_SETTING_WRITTEN_EVENT = 'product_configurator_setting.written';

    final public const PRODUCT_CONFIGURATOR_SETTING_DELETED_EVENT = 'product_configurator_setting.deleted';

    final public const PRODUCT_CONFIGURATOR_SETTING_LOADED_EVENT = 'product_configurator_setting.loaded';

    final public const PRODUCT_CONFIGURATOR_SETTING_SEARCH_RESULT_LOADED_EVENT = 'product_configurator_setting.search.result.loaded';

    final public const PRODUCT_CONFIGURATOR_SETTING_AGGREGATION_LOADED_EVENT = 'product_configurator_setting.aggregation.result.loaded';

    final public const PRODUCT_CONFIGURATOR_SETTING_ID_SEARCH_RESULT_LOADED_EVENT = 'product_configurator_setting.id.search.result.loaded';

    final public const PRODUCT_PRICE_WRITTEN_EVENT = 'product_price.written';

    final public const PRODUCT_PRICE_DELETED_EVENT = 'product_price.deleted';

    final public const PRODUCT_PRICE_LOADED_EVENT = 'product_price.loaded';

    final public const PRODUCT_PRICE_SEARCH_RESULT_LOADED_EVENT = 'product_price.search.result.loaded';

    final public const PRODUCT_PRICE_AGGREGATION_LOADED_EVENT = 'product_price.aggregation.result.loaded';

    final public const PRODUCT_PRICE_ID_SEARCH_RESULT_LOADED_EVENT = 'product_price.id.search.result.loaded';

    final public const PRODUCT_PROPERTY_WRITTEN_EVENT = 'product_property.written';

    final public const PRODUCT_PROPERTY_DELETED_EVENT = 'product_property.deleted';

    final public const PRODUCT_PROPERTY_LOADED_EVENT = 'product_property.loaded';

    final public const PRODUCT_PROPERTY_SEARCH_RESULT_LOADED_EVENT = 'product_property.search.result.loaded';

    final public const PRODUCT_PROPERTY_AGGREGATION_LOADED_EVENT = 'product_property.aggregation.result.loaded';

    final public const PRODUCT_PROPERTY_ID_SEARCH_RESULT_LOADED_EVENT = 'product_property.id.search.result.loaded';

    final public const PRODUCT_MANUFACTURER_WRITTEN_EVENT = 'product_manufacturer.written';

    final public const PRODUCT_MANUFACTURER_DELETED_EVENT = 'product_manufacturer.deleted';

    final public const PRODUCT_MANUFACTURER_LOADED_EVENT = 'product_manufacturer.loaded';

    final public const PRODUCT_MANUFACTURER_SEARCH_RESULT_LOADED_EVENT = 'product_manufacturer.search.result.loaded';

    final public const PRODUCT_MANUFACTURER_AGGREGATION_LOADED_EVENT = 'product_manufacturer.aggregation.result.loaded';

    final public const PRODUCT_MANUFACTURER_ID_SEARCH_RESULT_LOADED_EVENT = 'product_manufacturer.id.search.result.loaded';

    final public const PRODUCT_MANUFACTURER_TRANSLATION_WRITTEN_EVENT = 'product_manufacturer_translation.written';

    final public const PRODUCT_MANUFACTURER_TRANSLATION_DELETED_EVENT = 'product_manufacturer_translation.deleted';

    final public const PRODUCT_MANUFACTURER_TRANSLATION_LOADED_EVENT = 'product_manufacturer_translation.loaded';

    final public const PRODUCT_MANUFACTURER_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'product_manufacturer_translation.search.result.loaded';

    final public const PRODUCT_MANUFACTURER_TRANSLATION_AGGREGATION_LOADED_EVENT = 'product_manufacturer_translation.aggregation.result.loaded';

    final public const PRODUCT_MANUFACTURER_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'product_manufacturer_translation.id.search.result.loaded';

    final public const PRODUCT_MEDIA_WRITTEN_EVENT = 'product_media.written';

    final public const PRODUCT_MEDIA_DELETED_EVENT = 'product_media.deleted';

    final public const PRODUCT_MEDIA_LOADED_EVENT = 'product_media.loaded';

    final public const PRODUCT_MEDIA_SEARCH_RESULT_LOADED_EVENT = 'product_media.search.result.loaded';

    final public const PRODUCT_MEDIA_AGGREGATION_LOADED_EVENT = 'product_media.aggregation.result.loaded';

    final public const PRODUCT_MEDIA_ID_SEARCH_RESULT_LOADED_EVENT = 'product_media.id.search.result.loaded';

    final public const PRODUCT_SEARCH_KEYWORD_WRITTEN_EVENT = 'product_search_keyword.written';

    final public const PRODUCT_SEARCH_KEYWORD_DELETED_EVENT = 'product_search_keyword.deleted';

    final public const PRODUCT_SEARCH_KEYWORD_LOADED_EVENT = 'product_search_keyword.loaded';

    final public const PRODUCT_SEARCH_KEYWORD_SEARCH_RESULT_LOADED_EVENT = 'product_search_keyword.search.result.loaded';

    final public const PRODUCT_SEARCH_KEYWORD_AGGREGATION_LOADED_EVENT = 'product_search_keyword.aggregation.result.loaded';

    final public const PRODUCT_SEARCH_KEYWORD_ID_SEARCH_RESULT_LOADED_EVENT = 'product_search_keyword.id.search.result.loaded';

    final public const PRODUCT_SEO_CATEGORY_WRITTEN_EVENT = 'product_seo_category.written';

    final public const PRODUCT_SEO_CATEGORY_DELETED_EVENT = 'product_seo_category.deleted';

    final public const PRODUCT_SEO_CATEGORY_LOADED_EVENT = 'product_seo_category.loaded';

    final public const PRODUCT_SEO_CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'product_seo_category.search.result.loaded';

    final public const PRODUCT_SEO_CATEGORY_AGGREGATION_LOADED_EVENT = 'product_seo_category.aggregation.result.loaded';

    final public const PRODUCT_SEO_CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'product_seo_category.id.search.result.loaded';

    final public const PRODUCT_SERVICE_WRITTEN_EVENT = 'product_service.written';

    final public const PRODUCT_SERVICE_DELETED_EVENT = 'product_service.deleted';

    final public const PRODUCT_SERVICE_LOADED_EVENT = 'product_service.loaded';

    final public const PRODUCT_SERVICE_SEARCH_RESULT_LOADED_EVENT = 'product_service.search.result.loaded';

    final public const PRODUCT_SERVICE_AGGREGATION_LOADED_EVENT = 'product_service.aggregation.result.loaded';

    final public const PRODUCT_SERVICE_ID_SEARCH_RESULT_LOADED_EVENT = 'product_service.id.search.result.loaded';

    final public const PRODUCT_STREAM_WRITTEN_EVENT = 'product_stream.written';

    final public const PRODUCT_STREAM_DELETED_EVENT = 'product_stream.deleted';

    final public const PRODUCT_STREAM_LOADED_EVENT = 'product_stream.loaded';

    final public const PRODUCT_STREAM_SEARCH_RESULT_LOADED_EVENT = 'product_stream.search.result.loaded';

    final public const PRODUCT_STREAM_AGGREGATION_LOADED_EVENT = 'product_stream.aggregation.result.loaded';

    final public const PRODUCT_STREAM_ID_SEARCH_RESULT_LOADED_EVENT = 'product_stream.id.search.result.loaded';

    final public const PRODUCT_STREAM_ASSIGNMENT_WRITTEN_EVENT = 'product_stream_assignment.written';

    final public const PRODUCT_STREAM_ASSIGNMENT_DELETED_EVENT = 'product_stream_assignment.deleted';

    final public const PRODUCT_STREAM_ASSIGNMENT_LOADED_EVENT = 'product_stream_assignment.loaded';

    final public const PRODUCT_STREAM_ASSIGNMENT_SEARCH_RESULT_LOADED_EVENT = 'product_stream_assignment.search.result.loaded';

    final public const PRODUCT_STREAM_ASSIGNMENT_AGGREGATION_LOADED_EVENT = 'product_stream_assignment.aggregation.result.loaded';

    final public const PRODUCT_STREAM_ASSIGNMENT_ID_SEARCH_RESULT_LOADED_EVENT = 'product_stream_assignment.id.search.result.loaded';

    final public const PRODUCT_STREAM_TAB_WRITTEN_EVENT = 'product_stream_tab.written';

    final public const PRODUCT_STREAM_TAB_DELETED_EVENT = 'product_stream_tab.deleted';

    final public const PRODUCT_STREAM_TAB_LOADED_EVENT = 'product_stream_tab.loaded';

    final public const PRODUCT_STREAM_TAB_SEARCH_RESULT_LOADED_EVENT = 'product_stream_tab.search.result.loaded';

    final public const PRODUCT_STREAM_TAB_AGGREGATION_LOADED_EVENT = 'product_stream_tab.aggregation.result.loaded';

    final public const PRODUCT_STREAM_TAB_ID_SEARCH_RESULT_LOADED_EVENT = 'product_stream_tab.id.search.result.loaded';

    final public const PRODUCT_TRANSLATION_WRITTEN_EVENT = 'product_translation.written';

    final public const PRODUCT_TRANSLATION_DELETED_EVENT = 'product_translation.deleted';

    final public const PRODUCT_TRANSLATION_LOADED_EVENT = 'product_translation.loaded';

    final public const PRODUCT_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'product_translation.search.result.loaded';

    final public const PRODUCT_TRANSLATION_AGGREGATION_LOADED_EVENT = 'product_translation.aggregation.result.loaded';

    final public const PRODUCT_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'product_translation.id.search.result.loaded';

    final public const PRODUCT_OPTION_WRITTEN_EVENT = 'product_option.written';

    final public const PRODUCT_OPTION_DELETED_EVENT = 'product_option.deleted';

    final public const PRODUCT_OPTION_LOADED_EVENT = 'product_option.loaded';

    final public const PRODUCT_OPTION_SEARCH_RESULT_LOADED_EVENT = 'product_option.search.result.loaded';

    final public const PRODUCT_OPTION_AGGREGATION_LOADED_EVENT = 'product_option.aggregation.result.loaded';

    final public const PRODUCT_OPTION_ID_SEARCH_RESULT_LOADED_EVENT = 'product_option.id.search.result.loaded';

    final public const PRODUCT_CATEGORY_TREE_WRITTEN_EVENT = 'product_category_tree.written';

    final public const PRODUCT_CATEGORY_TREE_DELETED_EVENT = 'product_category_tree.deleted';

    final public const PRODUCT_CATEGORY_TREE_LOADED_EVENT = 'product_category_tree.loaded';

    final public const PRODUCT_CATEGORY_TREE_SEARCH_RESULT_LOADED_EVENT = 'product_category_tree.search.result.loaded';

    final public const PRODUCT_CATEGORY_TREE_AGGREGATION_LOADED_EVENT = 'product_category_tree.aggregation.result.loaded';

    final public const PRODUCT_CATEGORY_TREE_ID_SEARCH_RESULT_LOADED_EVENT = 'product_category_tree.id.search.result.loaded';

    final public const PRODUCT_REVIEW_LOADED = 'product_review.loaded';

    final public const PRODUCT_REVIEW_WRITTEN_EVENT = 'product_review.written';

    final public const PRODUCT_REVIEW_DELETED_EVENT = 'product_review.deleted';

    final public const PRODUCT_REVIEW_SEARCH_RESULT_LOADED_EVENT = 'product_review.search.result.loaded';

    final public const PRODUCT_REVIEW_AGGREGATION_LOADED_EVENT = 'product_review.aggregation.result.loaded';

    final public const PRODUCT_REVIEW_ID_SEARCH_RESULT_LOADED_EVENT = 'product_review.id.search.result.loaded';
}
