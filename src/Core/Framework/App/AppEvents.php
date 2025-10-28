<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AppEvents
{
    final public const APP_WRITTEN_EVENT = 'app.written';

    final public const APP_DELETED_EVENT = 'app.deleted';

    final public const APP_LOADED_EVENT = 'app.loaded';

    final public const APP_SEARCH_RESULT_LOADED_EVENT = 'app.search.result.loaded';

    final public const APP_AGGREGATION_LOADED_EVENT = 'app.aggregation.result.loaded';

    final public const APP_ID_SEARCH_RESULT_LOADED_EVENT = 'app.id.search.result.loaded';
}
