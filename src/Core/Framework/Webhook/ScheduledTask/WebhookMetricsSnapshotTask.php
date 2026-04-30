<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WebhookMetricsSnapshotTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'webhook.metrics.snapshot';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return Feature::isActive('TELEMETRY_METRICS') && Feature::isActive('WEBHOOKS_REWORK');
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
