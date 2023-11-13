<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('merchant-services')]
class CheckIntegrationChangedTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'usage_data.integration_changed';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 1 day
    }
}
