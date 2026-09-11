<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal
 */
#[Package('checkout')]
final class InAppPurchaseUpdateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'in-app-purchase.update';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return !($bag->has('shopware.deployment.air_gapped') && $bag->get('shopware.deployment.air_gapped'));
    }
}
