<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\Health\WebhookDispatchDecision;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Shopware\Tests\Integration\Core\Framework\Webhook\Health\EndpointHealthStateMachineMatrixTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see EndpointHealthStateMachineMatrixTest
 */
#[Package('framework')]
class WebhookHealthService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly WebhookOutboxStore $outboxStore,
        private readonly HealthConfig $config,
        private readonly ClockInterface $clock,
    ) {
    }

    public function gateFor(string $webhookId): WebhookDispatchDecision
    {
        if ($this->currentState($webhookId) === EndpointState::Healthy) {
            return WebhookDispatchDecision::Deliver;
        }

        return WebhookDispatchDecision::Hold;
    }

    public function recordSuccess(string $webhookId): void
    {
        // The guarded writes below absorb a concurrent state change after this read.
        $row = $this->connection->fetchAssociative(
            'SELECT wh.endpoint_state, wh.consecutive_transient_failures, wh.consecutive_non_transient_failures
             FROM webhook_health wh WHERE wh.webhook_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );
        $state = \is_array($row) ? EndpointState::from((string) $row['endpoint_state']) : EndpointState::Healthy;

        if ($state === EndpointState::Degraded && $this->promoteDegradedToHealthy($webhookId, keepFailureStreaks: false)) {
            return;
        }

        if (\is_array($row) && ((int) $row['consecutive_transient_failures'] > 0 || (int) $row['consecutive_non_transient_failures'] > 0)) {
            $cleared = (int) $this->connection->executeStatement(
                'UPDATE webhook_health
                 SET consecutive_transient_failures = 0, consecutive_non_transient_failures = 0, updated_at = :now
                 WHERE webhook_id = :id AND endpoint_state = :healthy',
                [
                    'now' => $this->now(),
                    'id' => Uuid::fromHexToBytes($webhookId),
                    'healthy' => EndpointState::Healthy->value,
                ]
            );

            if ($cleared > 0) {
                $this->mirrorBcColumns($webhookId);

                return;
            }
        }

        // Health rows are lazy, so also reconcile a fail-open HEALTHY webhook. Success must not
        // reactivate a legacy inactive webhook.
        $this->connection->executeStatement(
            'UPDATE webhook w
             LEFT JOIN webhook_health wh ON wh.webhook_id = w.id
             SET w.error_count = 0
             WHERE w.id = :id
               AND (wh.webhook_id IS NULL OR wh.endpoint_state = :healthy)
               AND w.error_count <> 0',
            [
                'id' => Uuid::fromHexToBytes($webhookId),
                'healthy' => EndpointState::Healthy->value,
            ]
        );
    }

    public function recordFailure(string $webhookId, ErrorClassification $classification, int $attempt): EndpointState
    {
        return match ($classification) {
            ErrorClassification::Success => throw WebhookException::unexpectedClassification($classification->value),
            ErrorClassification::NonTransientPayload => $this->currentState($webhookId),
            ErrorClassification::TransientNetwork,
            ErrorClassification::TransientServer,
            ErrorClassification::TransientRateLimit,
            ErrorClassification::TransientRedirect => $this->recordTransientFailure($webhookId, $attempt),
        };
    }

    public function tick(): void
    {
        $this->runDueReleases();

        foreach ($this->outboxStore->findWebhookIdsWithStrandedHolds() as $webhookId) {
            $this->outboxStore->resumeDeliveriesForWebhook($webhookId);
        }

        $this->outboxStore->cancelOrphanedHeldRows();
    }

    /**
     * Pre-rework `error_count` failure handling. Runs only with WEBHOOKS_REWORK off.
     */
    public function recordLegacyFailure(string $webhookId, WebhookFailureStrategy $strategy): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        if (!\is_array($row) || !$row['active']) {
            return;
        }

        $newCount = (int) $row['error_count'] + 1;

        $params = $strategy === WebhookFailureStrategy::DisableOnThreshold && $newCount >= WebhookFailureStrategy::MAX_ERROR_COUNT
            ? ['error_count' => 0, 'active' => 0]
            : ['error_count' => $newCount];

        $this->connection->update('webhook', $params, ['id' => Uuid::fromHexToBytes($webhookId)]);
    }

    /**
     * Pre-rework `error_count` reset. Runs only with WEBHOOKS_REWORK off.
     */
    public function resetErrorCount(string $webhookId): void
    {
        $this->connection->update('webhook', ['error_count' => 0], ['id' => Uuid::fromHexToBytes($webhookId)]);
    }

    private function runDueReleases(): void
    {
        $now = $this->now();

        /** @var list<string> $candidates */
        $candidates = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(webhook_id))
             FROM webhook_health
             WHERE endpoint_state = :degraded
               AND (cooldown_until IS NULL OR cooldown_until <= :now)',
            [
                'now' => $now,
                'degraded' => EndpointState::Degraded->value,
            ]
        );

        foreach ($candidates as $webhookId) {
            RetryableTransaction::retryable($this->connection, function () use ($webhookId, $now): void {
                // The row lock prevents concurrent ticks from releasing multiple trials.
                $row = $this->lockHealthRow($webhookId);
                if ($row === null
                    || (string) $row['endpoint_state'] !== EndpointState::Degraded->value
                    || ($row['cooldown_until'] !== null && (string) $row['cooldown_until'] > $now)
                ) {
                    return;
                }

                if ($this->outboxStore->hasClaimableOrRunningRows($webhookId)) {
                    return;
                }

                if ($this->outboxStore->releaseOneTrialLocked($webhookId)) {
                    return;
                }

                $this->promoteDegradedToHealthyLocked($webhookId, keepFailureStreaks: true);
            });
        }
    }

    private function recordTransientFailure(string $webhookId, int $attempt): EndpointState
    {
        $current = $this->currentState($webhookId);

        if ($current === EndpointState::Degraded) {
            return $this->advanceLadder($webhookId);
        }

        // Retries of the same delivery do not count towards endpoint health.
        if ($attempt > 1) {
            return $current;
        }

        return $this->recordHealthyTransientFailure($webhookId);
    }

    private function recordHealthyTransientFailure(string $webhookId): EndpointState
    {
        $threshold = $this->config->degradedThreshold;
        $now = $this->now();
        $firstCooldown = $this->cooldownAt(0);
        $webhookIdBytes = Uuid::fromHexToBytes($webhookId);

        $outcome = RetryableTransaction::retryable($this->connection, function () use ($webhookIdBytes, $threshold, $now, $firstCooldown): ?EndpointState {
            $this->ensureHealthRow($webhookIdBytes, $now);

            return $this->updateHealthyTransientFailure($webhookIdBytes, $threshold, $now, $firstCooldown);
        });

        if ($outcome === null) {
            return $this->currentState($webhookId);
        }

        if ($outcome === EndpointState::Degraded) {
            $this->outboxStore->pauseDeliveriesForWebhook($webhookId);
        }

        $this->mirrorBcColumns($webhookId);

        return $outcome;
    }

    private function updateHealthyTransientFailure(
        string $webhookIdBytes,
        int $threshold,
        string $now,
        string $firstCooldown,
    ): ?EndpointState {
        $incremented = (int) $this->connection->executeStatement(
            'UPDATE webhook_health
             SET consecutive_transient_failures = consecutive_transient_failures + 1, updated_at = :now
             WHERE webhook_id = :id AND endpoint_state = :healthy
               AND consecutive_transient_failures + 1 < :threshold',
            [
                'healthy' => EndpointState::Healthy->value,
                'now' => $now,
                'id' => $webhookIdBytes,
                'threshold' => $threshold,
            ]
        );
        if ($incremented > 0) {
            return EndpointState::Healthy;
        }

        $crossed = (int) $this->connection->executeStatement(
            'UPDATE webhook_health
             SET endpoint_state = :degraded, degraded_cycle_count = 0, cooldown_until = :firstCooldown,
                 consecutive_transient_failures = consecutive_transient_failures + 1, updated_at = :now
             WHERE webhook_id = :id AND endpoint_state = :healthy
               AND consecutive_transient_failures + 1 >= :threshold',
            [
                'degraded' => EndpointState::Degraded->value,
                'healthy' => EndpointState::Healthy->value,
                'firstCooldown' => $firstCooldown,
                'now' => $now,
                'id' => $webhookIdBytes,
                'threshold' => $threshold,
            ]
        );

        return $crossed > 0 ? EndpointState::Degraded : null;
    }

    /**
     * Health rows are created on first use; the conflict clause absorbs a concurrent writer.
     */
    private function ensureHealthRow(string $webhookIdBytes, string $now): void
    {
        $this->connection->executeStatement(
            'INSERT INTO webhook_health (webhook_id, endpoint_state, created_at)
             VALUES (:id, :healthy, :now)
             ON DUPLICATE KEY UPDATE webhook_id = webhook_id',
            [
                'id' => $webhookIdBytes,
                'healthy' => EndpointState::Healthy->value,
                'now' => $now,
            ]
        );
    }

    private function advanceLadder(string $webhookId): EndpointState
    {
        RetryableTransaction::retryable($this->connection, function () use ($webhookId): void {
            $row = $this->lockHealthRow($webhookId);
            if ($row === null || (string) $row['endpoint_state'] !== EndpointState::Degraded->value) {
                return;
            }

            $this->advanceLadderLocked($webhookId, $row);
        });

        return EndpointState::Degraded;
    }

    /**
     * @param array{endpoint_state: string, degraded_cycle_count: int|string, cooldown_until: string|null} $row
     */
    private function advanceLadderLocked(string $webhookId, array $row): void
    {
        $now = $this->now();
        $cooldownElapsed = $row['cooldown_until'] === null || (string) $row['cooldown_until'] <= $now;

        if (!$cooldownElapsed) {
            return;
        }

        $topIndex = \count($this->config->cooldownScheduleSeconds) - 1;
        $nextIndex = (int) $row['degraded_cycle_count'] + 1;
        $index = min($nextIndex, $topIndex);
        $this->connection->executeStatement(
            'UPDATE webhook_health
             SET degraded_cycle_count = :index, cooldown_until = :cooldown, updated_at = :now
             WHERE webhook_id = :id',
            [
                'index' => $index,
                'cooldown' => $this->cooldownAt($index),
                'now' => $now,
                'id' => Uuid::fromHexToBytes($webhookId),
            ]
        );
    }

    private function promoteDegradedToHealthy(string $webhookId, bool $keepFailureStreaks): bool
    {
        return RetryableTransaction::retryable($this->connection, function () use ($webhookId, $keepFailureStreaks): bool {
            $row = $this->lockHealthRow($webhookId);
            if ($row === null || (string) $row['endpoint_state'] !== EndpointState::Degraded->value) {
                return false;
            }

            return $this->promoteDegradedToHealthyLocked($webhookId, $keepFailureStreaks);
        });
    }

    private function promoteDegradedToHealthyLocked(string $webhookId, bool $keepFailureStreaks): bool
    {
        if (!$this->resetToHealthy($webhookId, $keepFailureStreaks)) {
            return false;
        }

        // Keep the health row locked until the backlog and BC mirror match the new state.
        $this->outboxStore->resumeDeliveriesForWebhook($webhookId);
        $this->mirrorBcColumns($webhookId);

        return true;
    }

    /**
     * @return array{endpoint_state: string, degraded_cycle_count: int|string, cooldown_until: string|null}|null
     */
    private function lockHealthRow(string $webhookId): ?array
    {
        /** @var array{endpoint_state: string, degraded_cycle_count: int|string, cooldown_until: string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT endpoint_state, degraded_cycle_count, cooldown_until
             FROM webhook_health WHERE webhook_id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        return $row === false ? null : $row;
    }

    private function resetToHealthy(string $webhookId, bool $keepFailureStreaks): bool
    {
        return $this->connection->executeStatement(
            'UPDATE webhook_health
             SET endpoint_state = :healthy,
                 consecutive_transient_failures = IF(:keepFailureStreaks = 1, consecutive_transient_failures, 0),
                 consecutive_non_transient_failures = IF(:keepFailureStreaks = 1, consecutive_non_transient_failures, 0),
                 degraded_cycle_count = 0, cooldown_until = NULL, suspended_since = NULL,
                 disabled_since = NULL, disabled_origin = NULL, updated_at = :now
             WHERE webhook_id = :id AND endpoint_state <> :healthy',
            [
                'healthy' => EndpointState::Healthy->value,
                'keepFailureStreaks' => (int) $keepFailureStreaks,
                'now' => $this->now(),
                'id' => Uuid::fromHexToBytes($webhookId),
            ]
        ) > 0;
    }

    private function mirrorBcColumns(string $webhookId): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook w
             JOIN webhook_health wh ON wh.webhook_id = w.id
             SET w.active = IF(wh.endpoint_state IN (:healthy, :degraded), 1, 0),
                 w.error_count = IF(
                     wh.endpoint_state = :healthy,
                     0,
                     GREATEST(wh.consecutive_transient_failures, wh.consecutive_non_transient_failures)
                 )
             WHERE w.id = :id',
            [
                'healthy' => EndpointState::Healthy->value,
                'degraded' => EndpointState::Degraded->value,
                'id' => Uuid::fromHexToBytes($webhookId),
            ]
        );
    }

    private function cooldownAt(int $index): string
    {
        $schedule = $this->config->cooldownScheduleSeconds;
        $seconds = $schedule[min($index, \count($schedule) - 1)];

        return $this->clock->now()
            ->modify(\sprintf('+%d seconds', $seconds))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function currentState(string $webhookId): EndpointState
    {
        $state = $this->connection->fetchOne(
            'SELECT endpoint_state FROM webhook_health WHERE webhook_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        // Missing health rows fail open during rollout.
        return $state === false ? EndpointState::Healthy : EndpointState::from((string) $state);
    }

    private function now(): string
    {
        return $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
