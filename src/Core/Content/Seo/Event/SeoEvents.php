<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Event;

class SeoEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const SEO_URL_WRITTEN_EVENT = 'seo_url.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const SEO_URL_DELETED_EVENT = 'seo_url.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const SEO_URL_LOADED_EVENT = 'seo_url.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const SEO_URL_SEARCH_RESULT_LOADED_EVENT = 'seo_url.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const SEO_URL_AGGREGATION_LOADED_EVENT = 'seo_url.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SEO_URL_ID_SEARCH_RESULT_LOADED_EVENT = 'seo_url.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const SEO_URL_TEMPLATE_WRITTEN_EVENT = 'seo_url_template.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const SEO_URL_TEMPLATE_DELETED_EVENT = 'seo_url_template.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const SEO_URL_TEMPLATE_LOADED_EVENT = 'seo_url_template.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const SEO_URL_TEMPLATE_SEARCH_RESULT_LOADED_EVENT = 'seo_url_template.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const SEO_URL_TEMPLATE_AGGREGATION_LOADED_EVENT = 'seo_url_template.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SEO_URL_TEMPLATE_ID_SEARCH_RESULT_LOADED_EVENT = 'seo_url_template.id.search.result.loaded';
}
