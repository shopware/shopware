<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
class CleanupCookieConsentLogTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'cookie_consent_log.cleanup';
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
