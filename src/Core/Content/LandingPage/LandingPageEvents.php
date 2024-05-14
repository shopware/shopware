<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage;

use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class LandingPageEvents
{
    final public const LANDING_PAGE_WRITTEN_EVENT = 'landing_page.written';

    final public const LANDING_PAGE_DELETED_EVENT = 'landing_page.deleted';

    final public const LANDING_PAGE_LOADED_EVENT = 'landing_page.loaded';

    final public const LANDING_PAGE_SEARCH_RESULT_LOADED_EVENT = 'landing_page.search.result.loaded';

    final public const LANDING_PAGE_AGGREGATION_LOADED_EVENT = 'landing_page.aggregation.result.loaded';

    final public const LANDING_PAGE_ID_SEARCH_RESULT_LOADED_EVENT = 'landing_page.id.search.result.loaded';

    final public const LANDING_PAGE_TRANSLATION_WRITTEN_EVENT = 'landing_page_translation.written';

    final public const LANDING_PAGE_TRANSLATION_DELETED_EVENT = 'landing_page_translation.deleted';

    final public const LANDING_PAGE_TRANSLATION_LOADED_EVENT = 'landing_page_translation.loaded';

    final public const LANDING_PAGE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'landing_page_translation.search.result.loaded';

    final public const LANDING_PAGE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'landing_page_translation.aggregation.result.loaded';

    final public const LANDING_PAGE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'landing_page_translation.id.search.result.loaded';

    final public const LANDING_PAGE_INDEXER_EVENT = LandingPageIndexerEvent::class;
}
