<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Hourly task that deletes expired platform profile cache entries.
 *
 * @internal
 */
#[Package('framework')]
class UcpProfileCacheCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'ucp.profile_cache_cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return 3600;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
