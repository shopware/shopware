<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class SnippetEvents
{
    final public const SNIPPET_WRITTEN_EVENT = 'snippet.written';

    final public const SNIPPET_DELETED_EVENT = 'snippet.deleted';

    final public const SNIPPET_LOADED_EVENT = 'snippet.loaded';

    final public const SNIPPET_SEARCH_RESULT_LOADED_EVENT = 'snippet.search.result.loaded';

    final public const SNIPPET_AGGREGATION_LOADED_EVENT = 'snippet.aggregation.result.loaded';

    final public const SNIPPET_ID_SEARCH_RESULT_LOADED_EVENT = 'snippet.id.search.result.loaded';

    /* SnippetSet */

    final public const SNIPPET_SET_WRITTEN_EVENT = 'snippet_set.written';

    final public const SNIPPET_SET_DELETED_EVENT = 'snippet_set.deleted';

    final public const SNIPPET_SET_LOADED_EVENT = 'snippet_set.loaded';

    final public const SNIPPET_SET_SEARCH_RESULT_LOADED_EVENT = 'snippet_set.search.result.loaded';

    final public const SNIPPET_SET_AGGREGATION_LOADED_EVENT = 'snippet_set.aggregation.result.loaded';

    final public const SNIPPET_SET_ID_SEARCH_RESULT_LOADED_EVENT = 'snippet_set.id.search.result.loaded';
}
