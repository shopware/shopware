<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class UserEvents
{
    final public const USER_WRITTEN_EVENT = 'user.written';

    final public const USER_DELETED_EVENT = 'user.deleted';

    final public const USER_LOADED_EVENT = 'user.loaded';

    final public const USER_SEARCH_RESULT_LOADED_EVENT = 'user.search.result.loaded';

    final public const USER_AGGREGATION_LOADED_EVENT = 'user.aggregation.result.loaded';

    final public const USER_ID_SEARCH_RESULT_LOADED_EVENT = 'user.id.search.result.loaded';

    final public const USER_ACCESS_KEY_WRITTEN_EVENT = 'user_access_key.written';

    final public const USER_ACCESS_KEY_DELETED_EVENT = 'user_access_key.deleted';

    final public const USER_ACCESS_KEY_LOADED_EVENT = 'user_access_key.loaded';

    final public const USER_ACCESS_KEY_SEARCH_RESULT_LOADED_EVENT = 'user_access_key.search.result.loaded';

    final public const USER_ACCESS_KEY_AGGREGATION_LOADED_EVENT = 'user_access_key.aggregation.result.loaded';

    final public const USER_ACCESS_KEY_ID_SEARCH_RESULT_LOADED_EVENT = 'user_access_key.id.search.result.loaded';
}
