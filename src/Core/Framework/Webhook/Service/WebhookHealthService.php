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
use Shopware\Core\Framework\Webhook\Health\HealthChange;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\Health\HealthRow;
use Shopware\Core\Framework\Webhook\Health\WebhookDispatchDecision;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
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
        $row = $this->fetchRow($webhookId);
        if ($row === null || $row->state === EndpointState::Healthy) {
            return WebhookDispatchDecision::Deliver;
        }

        if ($row->state === EndpointState::Disabled) {
            return WebhookDispatchDecision::Skip;
        }

        return WebhookDispatchDecision::Hold;
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

    /**
     * @return EndpointState|null null when $entry no longer owns its delivery row, so the failure is stale evidence
     */
    public function recordFailure(string $webhookId, ErrorClassification $classification, int $attempt, OutboxEntry $entry): ?EndpointState
    {
        if ($classification === ErrorClassification::Success) {
            throw WebhookException::unexpectedClassification($classification->value);
        }

        // A payload-specific failure is this message's problem, not the endpoint's.
        if (!$classification->isTransient()) {
            return $this->fetchRow($webhookId)->state ?? EndpointState::Healthy;
        }

        $stale = false;
        $change = $this->transition($webhookId, function (HealthRow $row) use ($attempt, $entry, &$stale): ?HealthRow {
            // Must stay first: the ownership lock is only safe to take after the health row's, never before.
            if (!$this->outboxStore->ownsRunningAttempt($entry)) {
                $stale = true;

                return null;
            }

            return $this->decideTransientFailure($row, $attempt);
        });

        if ($stale) {
            return null;
        }

        return $change->to->state ?? EndpointState::Healthy;
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
     * @return HealthChange|null the rows before and after, or null when the webhook no longer exists
     */
    private function transition(string $webhookId, \Closure $decide): ?HealthChange
    {
        $id = Uuid::fromHexToBytes($webhookId);

        $change = RetryableTransaction::retryable($this->connection, function () use ($id, $webhookId, $decide): ?HealthChange {
            $this->connection->executeStatement(
                'INSERT INTO webhook_health (webhook_id, endpoint_state, created_at)
                 SELECT id, :healthy, :now FROM webhook WHERE id = :id
                 ON DUPLICATE KEY UPDATE webhook_id = webhook_id',
                ['id' => $id, 'healthy' => EndpointState::Healthy->value, 'now' => $this->now()]
            );

            $row = $this->fetchRow($webhookId, forUpdate: true);
            if ($row === null) {
                return null;
            }

            $next = $decide($row);
            if ($next === null) {
                return new HealthChange($row, $row);
            }

            $this->writeRow($id, $next);

            // Held rows re-enter under the same lock, so a concurrent gate never sees HEALTHY with a paused backlog.
            if ($next->state === EndpointState::Healthy && $row->state !== EndpointState::Healthy) {
                $this->outboxStore->resumeDeliveriesForWebhook($webhookId);
            }

            return new HealthChange($row, $next);
        });

        if ($change === null || !$change->changedState()) {
            return $change;
        }

        if ($change->to->state === EndpointState::Degraded) {
            $this->outboxStore->pauseDeliveriesForWebhook($webhookId);
        }

        return $change;
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
            'UPDATE webhook_health wh
             JOIN webhook w ON w.id = wh.webhook_id
             SET wh.endpoint_state = :state,
                 wh.consecutive_transient_failures = :ctf,
                 wh.consecutive_non_transient_failures = :cnf,
                 wh.degraded_cycle_count = :cycle,
                 wh.cooldown_until = :cooldown,
                 wh.suspended_since = :suspended,
                 wh.disabled_since = :disabled,
                 wh.disabled_origin = :origin,
                 wh.updated_at = :now,
                 w.active = :active,
                 w.error_count = :errorCount
             WHERE wh.webhook_id = :id',
            [
                'state' => $row->state->value,
                'ctf' => $row->consecutiveTransientFailures,
                'cnf' => $row->consecutiveNonTransientFailures,
                'cycle' => $row->degradedCycleCount,
                'cooldown' => $row->cooldownUntil,
                'suspended' => $row->suspendedSince,
                'disabled' => $row->disabledSince,
                'origin' => $row->disabledOrigin?->value,
                'now' => $this->now(),
                'active' => (int) \in_array($row->state, [EndpointState::Healthy, EndpointState::Degraded], true),
                'errorCount' => $row->state === EndpointState::Healthy ? 0 : max($row->consecutiveTransientFailures, $row->consecutiveNonTransientFailures),
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
