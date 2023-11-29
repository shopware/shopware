<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\Events\FlowIndexerEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class FlowEvents
{
    final public const FLOW_WRITTEN_EVENT = 'flow.written';

    final public const FLOW_DELETED_EVENT = 'flow.deleted';

    final public const FLOW_LOADED_EVENT = 'flow.loaded';

    final public const FLOW_SEARCH_RESULT_LOADED_EVENT = 'flow.search.result.loaded';

    final public const FLOW_AGGREGATION_LOADED_EVENT = 'flow.aggregation.result.loaded';

    final public const FLOW_ID_SEARCH_RESULT_LOADED_EVENT = 'flow.id.search.result.loaded';

    final public const FLOW_INDEXER_EVENT = FlowIndexerEvent::class;

    final public const FLOW_SEQUENCE_WRITTEN_EVENT = 'flow_sequence.written';

    final public const FLOW_SEQUENCE_DELETED_EVENT = 'flow_sequence.deleted';

    final public const FLOW_SEQUENCE_LOADED_EVENT = 'flow_sequence.loaded';

    final public const FLOW_SEQUENCE_SEARCH_RESULT_LOADED_EVENT = 'flow_sequence.search.result.loaded';

    final public const FLOW_SEQUENCE_AGGREGATION_LOADED_EVENT = 'flow_sequence.aggregation.result.loaded';

    final public const FLOW_SEQUENCE_ID_SEARCH_RESULT_LOADED_EVENT = 'flow_sequence.id.search.result.loaded';
}
