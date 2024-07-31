<?php declare(strict_types=1);

namespace Shopware\Core\Services\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('core')]
class InstallServicesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'services.install';
    }

    public static function getDefaultInterval(): int
    {
        return 86_400; // 1 day
    }
}
