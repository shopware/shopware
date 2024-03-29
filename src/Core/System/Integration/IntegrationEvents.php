<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class IntegrationEvents
{
    final public const INTEGRATION_WRITTEN_EVENT = 'integration.written';

    final public const INTEGRATION_DELETED_EVENT = 'integration.deleted';

    final public const INTEGRATION_LOADED_EVENT = 'integration.loaded';

    final public const INTEGRATION_SEARCH_RESULT_LOADED_EVENT = 'integration.search.result.loaded';

    final public const INTEGRATION_AGGREGATION_LOADED_EVENT = 'integration.aggregation.result.loaded';

    final public const INTEGRATION_ID_SEARCH_RESULT_LOADED_EVENT = 'integration.id.search.result.loaded';
}
