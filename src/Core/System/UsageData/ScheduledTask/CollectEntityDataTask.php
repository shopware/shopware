<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('data-services')]
class CollectEntityDataTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'usage_data.entity_data.collect';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
