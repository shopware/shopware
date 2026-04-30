<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookCleanupKind;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookMetricLabel;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class WebhookCleanup
{
    private const BATCH_SIZE = 500;

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly StreamLockService $streamLockService,
        private readonly Meter $meter,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    public function removeOldLogs(): void
    {
        $entryLifetimeSeconds = $this->systemConfigService->getInt('core.webhook.entryLifetimeSeconds');

        if ($entryLifetimeSeconds === -1) {
            return;
        }

        Profiler::trace(
            'webhook::cleanup',
            function () use ($entryLifetimeSeconds): void {
                // Delete older webhook log entries where the webhook won't be called anymore
                $this->emitCleanupDeleted(
                    WebhookCleanupKind::SUCCESS_FAILED,
                    $this->deleteLogsOlderThanWithStatus($entryLifetimeSeconds, WebhookEventLogDefinition::STATUS_SUCCESS, WebhookEventLogDefinition::STATUS_FAILED)
                );

                // after double the entry lifetime, we also delete queued entries,
                // because we assume they are stuck in queued state (as we rely on message retry to retry failed webhooks)
                $this->emitCleanupDeleted(
                    WebhookCleanupKind::QUEUED_NO_DELIVERY,
                    $this->deleteQueuedLogsWithoutDeliveryOlderThan($entryLifetimeSeconds * 2)
                );

                $this->emitCleanupDeleted(WebhookCleanupKind::ORPHANED_STREAMS, $this->removeOrphanedStreams());
            },
            'webhook',
        );
    }

    private function removeOrphanedStreams(): int
    {
        $total = 0;
        do {
            $deleted = $this->streamLockService->deleteOrphanedStreams(self::BATCH_SIZE);
            $total += $deleted;
        } while ($deleted === self::BATCH_SIZE);

        return $total;
    }

    private function deleteLogsOlderThanWithStatus(int $entryLifetimeSeconds, string ...$status): int
    {
        $deleteBefore = $this->clock
            ->now()
            ->modify("- $entryLifetimeSeconds seconds")
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $total = 0;
        do {
            $deleted = (int) $this->connection->executeStatement(
                'DELETE FROM `webhook_event_log` WHERE `created_at` < :before AND `delivery_status` IN (:status) LIMIT :limit',
                [
                    'before' => $deleteBefore,
                    'status' => $status,
                    'limit' => self::BATCH_SIZE,
                ],
                [
                    'limit' => Types::INTEGER,
                    'status' => ArrayParameterType::STRING,
                ]
            );
            $total += $deleted;
        } while ($deleted === self::BATCH_SIZE);

        return $total;
    }

    private function deleteQueuedLogsWithoutDeliveryOlderThan(int $entryLifetimeSeconds): int
    {
        $deleteBefore = $this->clock
            ->now()
            ->modify("- $entryLifetimeSeconds seconds")
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $total = 0;
        do {
            $deleted = (int) $this->connection->executeStatement(
                'DELETE FROM `webhook_event_log`
                 WHERE `created_at` < :before
                   AND `delivery_status` = :status
                   AND NOT EXISTS (
                       SELECT 1
                       FROM `webhook_delivery`
                       WHERE `webhook_delivery`.`webhook_event_log_id` = `webhook_event_log`.`id`
                   )
                 LIMIT :limit',
                [
                    'before' => $deleteBefore,
                    'status' => WebhookEventLogDefinition::STATUS_QUEUED,
                    'limit' => self::BATCH_SIZE,
                ],
                [
                    'limit' => Types::INTEGER,
                ]
            );
            $total += $deleted;
        } while ($deleted === self::BATCH_SIZE);

        return $total;
    }

    private function emitCleanupDeleted(WebhookCleanupKind $kind, int $deletedCount): void
    {
        if ($deletedCount === 0) {
            return;
        }

        $this->meter->emit(new ConfiguredMetric(
            name: 'webhook.cleanup.deleted.total',
            value: $deletedCount,
            labels: [WebhookMetricLabel::KIND->value => $kind->value],
        ));
    }
}
