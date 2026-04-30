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
use Shopware\Core\Framework\Webhook\Telemetry\WebhookAuditAgeBucket;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookMetricLabel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @codeCoverageIgnore Integration tested with \Shopware\Tests\Integration\Core\Framework\Webhook\ScheduledTask\WebhookMetricsTaskHandlerTest
 */
#[AsMessageHandler(handles: WebhookAuditMetricsTask::class)]
#[Package('framework')]
final class WebhookAuditMetricsTaskHandler extends ScheduledTaskHandler
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

        $counts = $this->collector->countStuckInflight();

        foreach ([
            [WebhookAuditAgeBucket::FIFTEEN_MINUTES, $counts->fifteenMinutes],
            [WebhookAuditAgeBucket::ONE_HOUR, $counts->oneHour],
            [WebhookAuditAgeBucket::TWENTY_FOUR_HOURS, $counts->twentyFourHours],
        ] as [$bucket, $count]) {
            $this->meter->emit(new ConfiguredMetric(
                name: 'webhook.audit.stuck_inflight.rows',
                value: $count,
                labels: [WebhookMetricLabel::AGE_BUCKET->value => $bucket->value],
            ));
        }
    }
}
