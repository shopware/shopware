<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Webhook\ScheduledTask\WebhookAuditMetricsTask;
use Shopware\Core\Framework\Webhook\ScheduledTask\WebhookMetricsSnapshotTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[CoversClass(WebhookMetricsSnapshotTask::class)]
#[CoversClass(WebhookAuditMetricsTask::class)]
class WebhookMetricsTaskTest extends TestCase
{
    public function testMetadata(): void
    {
        static::assertSame('webhook.metrics.snapshot', WebhookMetricsSnapshotTask::getTaskName());
        static::assertSame(300, WebhookMetricsSnapshotTask::getDefaultInterval());
        static::assertTrue(WebhookMetricsSnapshotTask::shouldRescheduleOnFailure());

        static::assertSame('webhook.metrics.audit', WebhookAuditMetricsTask::getTaskName());
        static::assertSame(900, WebhookAuditMetricsTask::getDefaultInterval());
        static::assertTrue(WebhookAuditMetricsTask::shouldRescheduleOnFailure());
    }

    public function testRunsOnlyWhenTelemetryAndWebhookReworkAreActive(): void
    {
        $bag = new ParameterBag();

        Feature::withFeatureEnabled('TELEMETRY_METRICS', function () use ($bag): void {
            Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($bag): void {
                static::assertTrue(WebhookMetricsSnapshotTask::shouldRun($bag));
                static::assertTrue(WebhookAuditMetricsTask::shouldRun($bag));
            });

            Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($bag): void {
                static::assertFalse(WebhookMetricsSnapshotTask::shouldRun($bag));
                static::assertFalse(WebhookAuditMetricsTask::shouldRun($bag));
            });
        });

        Feature::withFeatureDisabled('TELEMETRY_METRICS', function () use ($bag): void {
            Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($bag): void {
                static::assertFalse(WebhookMetricsSnapshotTask::shouldRun($bag));
                static::assertFalse(WebhookAuditMetricsTask::shouldRun($bag));
            });
        });
    }
}
