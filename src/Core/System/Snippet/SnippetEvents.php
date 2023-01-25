<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class SnippetEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const SNIPPET_WRITTEN_EVENT = 'snippet.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const SNIPPET_DELETED_EVENT = 'snippet.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const SNIPPET_LOADED_EVENT = 'snippet.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const SNIPPET_SEARCH_RESULT_LOADED_EVENT = 'snippet.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const SNIPPET_AGGREGATION_LOADED_EVENT = 'snippet.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const SNIPPET_ID_SEARCH_RESULT_LOADED_EVENT = 'snippet.id.search.result.loaded';

    /* SnippetSet */
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const SNIPPET_SET_WRITTEN_EVENT = 'snippet_set.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const SNIPPET_SET_DELETED_EVENT = 'snippet_set.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const SNIPPET_SET_LOADED_EVENT = 'snippet_set.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const SNIPPET_SET_SEARCH_RESULT_LOADED_EVENT = 'snippet_set.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const SNIPPET_SET_AGGREGATION_LOADED_EVENT = 'snippet_set.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const SNIPPET_SET_ID_SEARCH_RESULT_LOADED_EVENT = 'snippet_set.id.search.result.loaded';
}
