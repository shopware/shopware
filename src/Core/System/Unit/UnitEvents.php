<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class UnitEvents
{
    final public const UNIT_WRITTEN_EVENT = 'unit.written';

    final public const UNIT_DELETED_EVENT = 'unit.deleted';

    final public const UNIT_LOADED_EVENT = 'unit.loaded';

    final public const UNIT_SEARCH_RESULT_LOADED_EVENT = 'unit.search.result.loaded';

    final public const UNIT_AGGREGATION_LOADED_EVENT = 'unit.aggregation.result.loaded';

    final public const UNIT_ID_SEARCH_RESULT_LOADED_EVENT = 'unit.id.search.result.loaded';

    final public const UNIT_TRANSLATION_WRITTEN_EVENT = 'unit_translation.written';

    final public const UNIT_TRANSLATION_DELETED_EVENT = 'unit_translation.deleted';

    final public const UNIT_TRANSLATION_LOADED_EVENT = 'unit_translation.loaded';

    final public const UNIT_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'unit_translation.search.result.loaded';

    final public const UNIT_TRANSLATION_AGGREGATION_LOADED_EVENT = 'unit_translation.aggregation.result.loaded';

    final public const UNIT_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'unit_translation.id.search.result.loaded';
}
