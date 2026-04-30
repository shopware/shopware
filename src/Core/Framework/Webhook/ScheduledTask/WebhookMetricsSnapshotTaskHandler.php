<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookDeliveryStatus;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookMetricLabel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @codeCoverageIgnore Integration tested with \Shopware\Tests\Integration\Core\Framework\Webhook\ScheduledTask\WebhookMetricsTaskHandlerTest
 */
#[AsMessageHandler(handles: WebhookMetricsSnapshotTask::class)]
#[Package('framework')]
final class WebhookMetricsSnapshotTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly WebhookMetricsCollector $collector,
        private readonly Meter $meter,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        if (!Feature::isActive('TELEMETRY_METRICS') || !Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $snapshot = $this->collector->snapshotQueueRowsByStatus();

        foreach ([
            [WebhookDeliveryStatus::QUEUED, $snapshot->queued],
            [WebhookDeliveryStatus::PENDING_RETRY, $snapshot->pendingRetry],
            [WebhookDeliveryStatus::RUNNING, $snapshot->running],
        ] as [$status, $gauge]) {
            $this->meter->emit(new ConfiguredMetric(
                name: 'webhook.queue.rows',
                value: $gauge->rows,
                labels: [WebhookMetricLabel::STATUS->value => $status->value],
            ));
            $this->meter->emit(new ConfiguredMetric(
                name: 'webhook.queue.oldest_age_seconds',
                value: $gauge->oldestAgeSeconds,
                labels: [WebhookMetricLabel::STATUS->value => $status->value],
            ));
        }

        $staleStreams = $this->collector->countStaleStreams();
        $this->meter->emit(new ConfiguredMetric(
            name: 'webhook.stream.stale.rows',
            value: $staleStreams,
        ));
    }
}
