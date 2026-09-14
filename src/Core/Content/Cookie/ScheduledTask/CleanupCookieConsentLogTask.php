<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ScheduledTask;

use Shopware\Core\Content\Cookie\ConsentLog\NullCookieConsentLogStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

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

    /**
     * Nothing to clean up while no decisions are recorded
     */
    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return $bag->get('shopware.cookie_consent.log_storage') !== NullCookieConsentLogStorage::NAME;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
