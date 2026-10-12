<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
class McpToolsetSessionCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'mcp_toolset_session.cleanup';
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
