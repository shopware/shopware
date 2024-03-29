<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class SeoEvents
{
    final public const SEO_URL_WRITTEN_EVENT = 'seo_url.written';

    final public const SEO_URL_DELETED_EVENT = 'seo_url.deleted';

    final public const SEO_URL_LOADED_EVENT = 'seo_url.loaded';

    final public const SEO_URL_SEARCH_RESULT_LOADED_EVENT = 'seo_url.search.result.loaded';

    final public const SEO_URL_AGGREGATION_LOADED_EVENT = 'seo_url.aggregation.result.loaded';

    final public const SEO_URL_ID_SEARCH_RESULT_LOADED_EVENT = 'seo_url.id.search.result.loaded';

    final public const SEO_URL_TEMPLATE_WRITTEN_EVENT = 'seo_url_template.written';

    final public const SEO_URL_TEMPLATE_DELETED_EVENT = 'seo_url_template.deleted';

    final public const SEO_URL_TEMPLATE_LOADED_EVENT = 'seo_url_template.loaded';

    final public const SEO_URL_TEMPLATE_SEARCH_RESULT_LOADED_EVENT = 'seo_url_template.search.result.loaded';

    final public const SEO_URL_TEMPLATE_AGGREGATION_LOADED_EVENT = 'seo_url_template.aggregation.result.loaded';

    final public const SEO_URL_TEMPLATE_ID_SEARCH_RESULT_LOADED_EVENT = 'seo_url_template.id.search.result.loaded';
}
