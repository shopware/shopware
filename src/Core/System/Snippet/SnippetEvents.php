<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

class SnippetEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const SNIPPET_WRITTEN_EVENT = 'snippet.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const SNIPPET_DELETED_EVENT = 'snippet.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const SNIPPET_LOADED_EVENT = 'snippet.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const SNIPPET_SEARCH_RESULT_LOADED_EVENT = 'snippet.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const SNIPPET_AGGREGATION_LOADED_EVENT = 'snippet.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SNIPPET_ID_SEARCH_RESULT_LOADED_EVENT = 'snippet.id.search.result.loaded';
}
