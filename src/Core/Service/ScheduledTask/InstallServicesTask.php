<?php declare(strict_types=1);

namespace Shopware\Core\Service\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('core')]
class InstallServicesTask extends ScheduledTask
{
    private const ONE_DAY_IN_SECONDS = 86_400;
    
    public static function getTaskName(): string
    {
        return 'services.install';
    }

    public static function getDefaultInterval(): int
    {
        return self::ONE_DAY_IN_SECONDS;
    }
}
