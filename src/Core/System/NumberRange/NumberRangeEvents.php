<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class NumberRangeEvents
{
    final public const NUMBER_RANGE_WRITTEN_EVENT = 'number_range.written';

    final public const NUMBER_RANGE_DELETED_EVENT = 'number_range.deleted';

    final public const NUMBER_RANGE_LOADED_EVENT = 'number_range.loaded';

    final public const NUMBER_RANGE_SEARCH_RESULT_LOADED_EVENT = 'number_range.search.result.loaded';

    final public const NUMBER_RANGE_AGGREGATION_LOADED_EVENT = 'number_range.aggregation.result.loaded';

    final public const NUMBER_RANGE_ID_SEARCH_RESULT_LOADED_EVENT = 'number_range.id.search.result.loaded';

    final public const NUMBER_RANGE_STATE_WRITTEN_EVENT = 'number_range_state.written';

    final public const NUMBER_RANGE_STATE_DELETED_EVENT = 'number_range_state.deleted';

    final public const NUMBER_RANGE_STATE_LOADED_EVENT = 'number_range_state.loaded';

    final public const NUMBER_RANGE_STATE_SEARCH_RESULT_LOADED_EVENT = 'number_range_state.search.result.loaded';

    final public const NUMBER_RANGE_STATE_AGGREGATION_LOADED_EVENT = 'number_range_state.aggregation.result.loaded';

    final public const NUMBER_RANGE_GENERATED = 'number_range.generated';
}
