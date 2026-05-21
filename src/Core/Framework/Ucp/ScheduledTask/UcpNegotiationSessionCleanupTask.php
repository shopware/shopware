<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Daily cleanup of negotiation sessions that have not been used for 30 days.
 *
 * @internal
 */
#[Package('framework')]
class UcpNegotiationSessionCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'ucp.negotiation_session_cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
