<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * The single clock for the webhook health model. Every time-based duty (trial releases,
 * idle promotion, the 7-day retirement, crash-leftover cleanup, stale-hold healing) runs
 * on this tick — see {@see EndpointLifecycle::tick()}.
 * 60 s gives the smallest cooldown tier (300 s) 5x headroom. Runs only under WEBHOOKS_REWORK.
 *
 * @internal
 */
#[Package('framework')]
class WebhookHealthTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'webhook.health';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return Feature::isActive('WEBHOOKS_REWORK');
    }
}
