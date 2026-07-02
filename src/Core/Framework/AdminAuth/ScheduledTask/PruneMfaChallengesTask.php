<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('framework')]
class PruneMfaChallengesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'admin_auth.prune_mfa_challenges';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }
}
