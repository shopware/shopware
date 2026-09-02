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
use Shopware\Core\Framework\Webhook\Health\HealthRow;
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
        // A missing health row reads as HEALTHY, so dispatch is never silently blocked.
        $state = $this->fetchRow($webhookId)->state ?? EndpointState::Healthy;

        return $state === EndpointState::Healthy ? WebhookDispatchDecision::Deliver : WebhookDispatchDecision::Hold;
    }

    public function recordSuccess(string $webhookId): void
    {
        $row = $this->fetchRow($webhookId);
        if ($row === null) {
            // Health rows are lazy; a legacy error_count may still be stale on a row-less webhook.
            $this->connection->executeStatement(
                'UPDATE webhook SET error_count = 0 WHERE id = :id AND error_count <> 0',
                ['id' => Uuid::fromHexToBytes($webhookId)]
            );

            return;
        }

        if ($row->state === EndpointState::Healthy && $row->consecutiveTransientFailures === 0 && $row->consecutiveNonTransientFailures === 0) {
            return;
        }

        // Any 2xx clears both failure streaks; DEGRADED → HEALTHY also resumes the held backlog.
        $this->transition($webhookId, static fn (HealthRow $row): HealthRow => $row->toHealthy(keepStreaks: false));
    }

    public function recordFailure(string $webhookId, ErrorClassification $classification, int $attempt): EndpointState
    {
        if ($classification === ErrorClassification::Success) {
            throw WebhookException::unexpectedClassification($classification->value);
        }

        // A payload-specific failure is this message's problem, not the endpoint's.
        if (!$classification->isTransient()) {
            return $this->fetchRow($webhookId)->state ?? EndpointState::Healthy;
        }

        $row = $this->transition($webhookId, fn (HealthRow $row): ?HealthRow => $this->decideTransientFailure($row, $attempt));

        return $row->state ?? EndpointState::Healthy;
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

    private function decideTransientFailure(HealthRow $row, int $attempt): ?HealthRow
    {
        $next = clone $row;

        if ($row->state === EndpointState::Degraded) {
            // Only a released trial moves the ladder; a result landing inside the cooldown is a straggler.
            if (!$row->cooldownElapsed($this->now())) {
                return null;
            }

            $next->degradedCycleCount = min($row->degradedCycleCount + 1, $this->topTier());
            $next->cooldownUntil = $this->cooldownAt($next->degradedCycleCount);

            return $next;
        }

        // Retries of one delivery count as one failure.
        if ($attempt > 1) {
            return null;
        }

        ++$next->consecutiveTransientFailures;
        if ($next->consecutiveTransientFailures >= $this->config->degradedThreshold) {
            $next->state = EndpointState::Degraded;
            $next->degradedCycleCount = 0;
            $next->cooldownUntil = $this->cooldownAt(0);
        }

        return $next;
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
            $this->transition($webhookId, function (HealthRow $row) use ($webhookId, $now): ?HealthRow {
                if ($row->state !== EndpointState::Degraded || !$row->cooldownElapsed($now)) {
                    return null;
                }

                // One release at a time: a trial still in flight, or one released now, ends this tick's duty.
                if ($this->outboxStore->hasClaimableOrRunningRows($webhookId) || $this->outboxStore->releaseOneTrialLocked($webhookId)) {
                    return null;
                }

                // Nothing held, nothing in flight: idle promotion. Unproven streaks stay.
                return $row->toHealthy(keepStreaks: true);
            });
        }
    }

    /**
     * The one write path: lock the row, let $decide compute the next row, store it, mirror the legacy
     * columns and flip the backlog. $decide returns null to leave the row untouched.
     *
     * @param \Closure(HealthRow): ?HealthRow $decide
     *
     * @return HealthRow|null the row after the call, or null when the webhook no longer exists
     */
    private function transition(string $webhookId, \Closure $decide): ?HealthRow
    {
        $id = Uuid::fromHexToBytes($webhookId);

        $rows = RetryableTransaction::retryable($this->connection, function () use ($id, $webhookId, $decide): array {
            $this->connection->executeStatement(
                'INSERT INTO webhook_health (webhook_id, endpoint_state, created_at)
                 SELECT id, :healthy, :now FROM webhook WHERE id = :id
                 ON DUPLICATE KEY UPDATE webhook_id = webhook_id',
                ['id' => $id, 'healthy' => EndpointState::Healthy->value, 'now' => $this->now()]
            );

            $row = $this->fetchRow($webhookId, forUpdate: true);
            if ($row === null) {
                return [null, null];
            }

            $next = $decide($row);
            if ($next === null) {
                return [$row, $row];
            }

            $this->writeRow($id, $next);
            $this->mirrorBcColumns($id);

            // Held rows re-enter under the same lock, so a concurrent gate never sees HEALTHY with a paused backlog.
            if ($next->state === EndpointState::Healthy && $row->state !== EndpointState::Healthy) {
                $this->outboxStore->resumeDeliveriesForWebhook($webhookId);
            }

            return [$row, $next];
        });

        [$from, $to] = $rows;
        if ($from !== null && $to !== null && $from->state === EndpointState::Healthy && $to->state === EndpointState::Degraded) {
            $this->outboxStore->pauseDeliveriesForWebhook($webhookId);
        }

        return $to;
    }

    private function fetchRow(string $webhookId, bool $forUpdate = false): ?HealthRow
    {
        $row = $this->connection->fetchAssociative(
            \sprintf(
                'SELECT endpoint_state, consecutive_transient_failures, consecutive_non_transient_failures,
                        degraded_cycle_count, cooldown_until, suspended_since, disabled_since, disabled_origin
                 FROM webhook_health WHERE webhook_id = :id%s',
                $forUpdate ? ' FOR UPDATE' : ''
            ),
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        return $row === false ? null : HealthRow::fromRow($row);
    }

    private function writeRow(string $id, HealthRow $row): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_health
             SET endpoint_state = :state,
                 consecutive_transient_failures = :ctf,
                 consecutive_non_transient_failures = :cnf,
                 degraded_cycle_count = :cycle,
                 cooldown_until = :cooldown,
                 suspended_since = :suspended,
                 disabled_since = :disabled,
                 disabled_origin = :origin,
                 updated_at = :now
             WHERE webhook_id = :id',
            [
                'state' => $row->state->value,
                'ctf' => $row->consecutiveTransientFailures,
                'cnf' => $row->consecutiveNonTransientFailures,
                'cycle' => $row->degradedCycleCount,
                'cooldown' => $row->cooldownUntil,
                'suspended' => $row->suspendedSince,
                'disabled' => $row->disabledSince,
                'origin' => $row->disabledOrigin,
                'now' => $this->now(),
                'id' => $id,
            ]
        );
    }

    private function mirrorBcColumns(string $id): void
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
                'id' => $id,
            ]
        );
    }

    private function topTier(): int
    {
        return \count($this->config->cooldownScheduleSeconds) - 1;
    }

    private function cooldownAt(int $index): string
    {
        $seconds = $this->config->cooldownScheduleSeconds[min($index, $this->topTier())];

        return $this->clock->now()
            ->modify(\sprintf('+%d seconds', $seconds))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function now(): string
    {
        return $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
