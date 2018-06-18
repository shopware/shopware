<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

class SnippetEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const SNIPPET_WRITTEN_EVENT = 'snippet.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const SNIPPET_DELETED_EVENT = 'snippet.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const SNIPPET_LOADED_EVENT = 'snippet.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const SNIPPET_SEARCH_RESULT_LOADED_EVENT = 'snippet.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const SNIPPET_AGGREGATION_LOADED_EVENT = 'snippet.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const SNIPPET_ID_SEARCH_RESULT_LOADED_EVENT = 'snippet.id.search.result.loaded';
}