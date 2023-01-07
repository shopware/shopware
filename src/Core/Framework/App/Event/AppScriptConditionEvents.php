<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

/**
 * @package core
 */
class AppScriptConditionEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const APP_SCRIPT_CONDITION_WRITTEN_EVENT = 'app_script_condition.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const APP_SCRIPT_CONDITION_DELETED_EVENT = 'app_script_condition.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const APP_SCRIPT_CONDITION_LOADED_EVENT = 'app_script_condition.loaded';
}
