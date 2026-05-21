<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Daily task that transitions UCP signing keys from `retiring` to `retired`
 * after a 24-hour grace period. See ADR 2026-05-20-ucp-jwt-key-storage-and-rotation.
 *
 * @internal
 */
#[Package('framework')]
class UcpKeyRetirementTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'ucp.key_retirement';
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
