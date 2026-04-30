<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\ScheduledTask\WebhookAuditMetricsTaskHandler;
use Shopware\Core\Framework\Webhook\ScheduledTask\WebhookMetricsCollector;
use Shopware\Core\Framework\Webhook\ScheduledTask\WebhookMetricsSnapshotTaskHandler;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookAuditAgeBucket;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookDeliveryStatus;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookMetricLabel;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;

/**
 * End-to-end coverage for the snapshot/audit handlers — DB rows in, configured
 * metrics out via the real telemetry pipeline. The collector's SQL is pinned by
 * \Shopware\Tests\Integration\Core\Framework\Webhook\ScheduledTask\WebhookMetricsCollectorTest;
 * this suite asserts the handler-side mapping (status/age-bucket → label) and the
 * feature-flag gate.
 *
 * @internal
 */
class WebhookMetricsTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private MockClock $clock;

    private WebhookMetricsCollector $collector;

    private IdsCollection $ids;

    private TraceableTransport $transport;

    private Meter $meter;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-30 12:00:00'));
        $this->collector = new WebhookMetricsCollector($this->connection, $this->clock);
        $this->ids = new IdsCollection();
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook_stream');

        $transports = static::getContainer()->get(TransportCollection::class);
        static::assertInstanceOf(TransportCollection::class, $transports);
        $traceable = null;
        foreach ($transports as $transport) {
            if ($transport instanceof TraceableTransport) {
                $traceable = $transport;
                break;
            }
        }
        static::assertInstanceOf(TraceableTransport::class, $traceable);
        $traceable->reset();
        $this->transport = $traceable;
        $this->meter = static::getContainer()->get(Meter::class);
    }

    public function testSnapshotHandlerEmitsRowsAndAgePerStatusAndStaleStreamCount(): void
    {
        $this->insertQueueRow('evt-queued', WebhookEventLogDefinition::STATUS_QUEUED, '-90 seconds');
        $this->insertPendingRetryRow('evt-pending', '-45 seconds');
        $this->insertQueueRow('evt-running', WebhookEventLogDefinition::STATUS_RUNNING, '-12 seconds');
        $this->insertStaleStream('worker-1', '-1 minute');
        $this->insertStaleStream('worker-2', '-2 minutes');

        $handler = $this->newSnapshotHandler();

        Feature::withFeatureEnabled('TELEMETRY_METRICS', function () use ($handler): void {
            Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $handler->run());
        });

        static::assertSame(1, $this->countMetric('webhook.queue.rows', [WebhookMetricLabel::STATUS->value => WebhookDeliveryStatus::QUEUED->value], 1));
        static::assertSame(1, $this->countMetric('webhook.queue.rows', [WebhookMetricLabel::STATUS->value => WebhookDeliveryStatus::PENDING_RETRY->value], 1));
        static::assertSame(1, $this->countMetric('webhook.queue.rows', [WebhookMetricLabel::STATUS->value => WebhookDeliveryStatus::RUNNING->value], 1));

        static::assertEqualsWithDelta(90, $this->metricValue('webhook.queue.oldest_age_seconds', [WebhookMetricLabel::STATUS->value => WebhookDeliveryStatus::QUEUED->value]), 2);
        static::assertEqualsWithDelta(45, $this->metricValue('webhook.queue.oldest_age_seconds', [WebhookMetricLabel::STATUS->value => WebhookDeliveryStatus::PENDING_RETRY->value]), 2);
        static::assertEqualsWithDelta(12, $this->metricValue('webhook.queue.oldest_age_seconds', [WebhookMetricLabel::STATUS->value => WebhookDeliveryStatus::RUNNING->value]), 2);

        static::assertSame(2, $this->metricValue('webhook.stream.stale.rows', []));
    }

    public function testSnapshotHandlerIsAGuardedNoOpWhenFlagsAreOff(): void
    {
        $this->insertQueueRow('evt-queued', WebhookEventLogDefinition::STATUS_QUEUED, '-90 seconds');

        $handler = $this->newSnapshotHandler();

        Feature::withFeatureEnabled('TELEMETRY_METRICS', function () use ($handler): void {
            Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $handler->run());
        });

        static::assertSame([], $this->findMetrics('webhook.queue.rows'));
        static::assertSame([], $this->findMetrics('webhook.queue.oldest_age_seconds'));
        static::assertSame([], $this->findMetrics('webhook.stream.stale.rows'));
    }

    public function testAuditHandlerEmitsCumulativeStuckInflightBucketsByAge(): void
    {
        // 16m, 65m, 25h → cumulative buckets {15m: 3, 1h: 2, 24h: 1}.
        $this->insertStuckRow('evt-16m', WebhookEventLogDefinition::STATUS_RUNNING, '-16 minutes');
        $this->insertStuckRow('evt-65m', WebhookEventLogDefinition::STATUS_RUNNING, '-65 minutes');
        $this->insertStuckRow('evt-25h', WebhookEventLogDefinition::STATUS_RUNNING, '-25 hours');

        $handler = $this->newAuditHandler();

        Feature::withFeatureEnabled('TELEMETRY_METRICS', function () use ($handler): void {
            Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $handler->run());
        });

        static::assertSame(3, $this->metricValue('webhook.audit.stuck_inflight.rows', [WebhookMetricLabel::AGE_BUCKET->value => WebhookAuditAgeBucket::FIFTEEN_MINUTES->value]));
        static::assertSame(2, $this->metricValue('webhook.audit.stuck_inflight.rows', [WebhookMetricLabel::AGE_BUCKET->value => WebhookAuditAgeBucket::ONE_HOUR->value]));
        static::assertSame(1, $this->metricValue('webhook.audit.stuck_inflight.rows', [WebhookMetricLabel::AGE_BUCKET->value => WebhookAuditAgeBucket::TWENTY_FOUR_HOURS->value]));
    }

    public function testAuditHandlerIsAGuardedNoOpWhenFlagsAreOff(): void
    {
        $this->insertStuckRow('evt-25h', WebhookEventLogDefinition::STATUS_RUNNING, '-25 hours');

        $handler = $this->newAuditHandler();

        Feature::withFeatureEnabled('TELEMETRY_METRICS', function () use ($handler): void {
            Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $handler->run());
        });

        static::assertSame([], $this->findMetrics('webhook.audit.stuck_inflight.rows'));
    }

    private function newSnapshotHandler(): WebhookMetricsSnapshotTaskHandler
    {
        return new WebhookMetricsSnapshotTaskHandler(
            $this->createMock(EntityRepository::class),
            new NullLogger(),
            $this->collector,
            $this->meter,
        );
    }

    private function newAuditHandler(): WebhookAuditMetricsTaskHandler
    {
        return new WebhookAuditMetricsTaskHandler(
            $this->createMock(EntityRepository::class),
            new NullLogger(),
            $this->collector,
            $this->meter,
        );
    }

    /**
     * @param array<string, scalar> $labels
     *
     * @return list<Metric>
     */
    private function findMetrics(string $name, array $labels = []): array
    {
        return array_values(array_filter(
            $this->transport->getEmittedMetrics(),
            static function (Metric $metric) use ($name, $labels): bool {
                if ($metric->name !== $name) {
                    return false;
                }
                foreach ($labels as $key => $value) {
                    if (($metric->labels[$key] ?? null) !== $value) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * @param array<string, scalar> $labels
     */
    private function countMetric(string $name, array $labels, int $expectedAtLeast): int
    {
        $matches = $this->findMetrics($name, $labels);
        static::assertGreaterThanOrEqual(
            $expectedAtLeast,
            \count($matches),
            \sprintf('Expected at least %d emission(s) of "%s" matching %s', $expectedAtLeast, $name, json_encode($labels, \JSON_THROW_ON_ERROR)),
        );

        return \count($matches);
    }

    /**
     * @param array<string, scalar> $labels
     */
    private function metricValue(string $name, array $labels): int|float
    {
        $matches = $this->findMetrics($name, $labels);
        static::assertCount(1, $matches, \sprintf('Expected exactly one emission of "%s" matching %s', $name, json_encode($labels, \JSON_THROW_ON_ERROR)));

        return $matches[0]->value;
    }

    private function insertQueueRow(string $eventKey, string $deliveryStatus, string $relativeAge): void
    {
        $timestamp = $this->clock->now()->modify($relativeAge)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $eventLogStatus = match ($deliveryStatus) {
            WebhookEventLogDefinition::STATUS_SUCCESS, WebhookEventLogDefinition::STATUS_FAILED => $deliveryStatus,
            default => WebhookEventLogDefinition::STATUS_RUNNING,
        };

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => $eventLogStatus,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $timestamp,
        ]);
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => null,
            'partition_key' => Hasher::hashBinary('app-' . $eventKey, 'xxh128'),
            'delivery_status' => $deliveryStatus,
            'execution_count' => 1,
            'last_attempt_at' => $deliveryStatus === WebhookEventLogDefinition::STATUS_RUNNING ? $timestamp : null,
            'next_retry_at' => null,
            'created_at' => $timestamp,
        ]);
    }

    private function insertPendingRetryRow(string $eventKey, string $relativeRetryAt): void
    {
        $now = $this->clock->now();
        $nextRetryAt = $now->modify($relativeRetryAt)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $createdAt = $now->modify('-30 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $createdAt,
        ]);
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => null,
            'partition_key' => Hasher::hashBinary('app-' . $eventKey, 'xxh128'),
            'delivery_status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            'execution_count' => 2,
            'last_attempt_at' => $now->modify('-15 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'next_retry_at' => $nextRetryAt,
            'created_at' => $createdAt,
        ]);
    }

    private function insertStuckRow(string $eventKey, string $deliveryStatus, string $relativeAge): void
    {
        $timestamp = $this->clock->now()->modify($relativeAge)->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => WebhookEventLogDefinition::STATUS_RUNNING,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $timestamp,
        ]);
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => null,
            'partition_key' => Hasher::hashBinary('app-' . $eventKey, 'xxh128'),
            'delivery_status' => $deliveryStatus,
            'execution_count' => 1,
            'last_attempt_at' => $timestamp,
            'next_retry_at' => null,
            'created_at' => $timestamp,
        ]);
    }

    private function insertStaleStream(string $workerId, string $relativeExpiresAt): void
    {
        $expiresAt = $this->clock->now()->modify($relativeExpiresAt)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $partitionKey = Hasher::hashBinary('partition-' . $workerId, 'xxh128');

        $this->connection->insert('webhook_stream', [
            'id' => $this->ids->getBytes('stream-' . $workerId),
            'partition_key' => $partitionKey,
            'locked_by' => $workerId,
            'lock_expires_at' => $expiresAt,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
