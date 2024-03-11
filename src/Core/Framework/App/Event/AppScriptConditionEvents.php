<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AppScriptConditionEvents
{
    final public const APP_SCRIPT_CONDITION_WRITTEN_EVENT = 'app_script_condition.written';

    final public const APP_SCRIPT_CONDITION_DELETED_EVENT = 'app_script_condition.deleted';

    final public const APP_SCRIPT_CONDITION_LOADED_EVENT = 'app_script_condition.loaded';
}
