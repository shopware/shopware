<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Event\WebhookDegradedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HealthChange;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\Health\HealthRow;
use Shopware\Core\Framework\Webhook\Health\SuspensionCause;
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
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
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

        if ($row->state === EndpointState::Degraded && $row->suspendedSince === null) {
            return WebhookDispatchDecision::Hold;
        }

        // During a suspension incident only a due trial is delivered; other events are shed.
        return $this->admitIncidentTrial($webhookId, $row);
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

        $change = $this->transition($webhookId, fn (HealthRow $row): ?HealthRow => $this->decideSuccess($row));
        $this->emit($webhookId, $change, trigger: WebhookActivationTrigger::Trial);
    }

    public function recordFailure(string $webhookId, ErrorClassification $classification, int $attempt): EndpointState
    {
        if ($classification === ErrorClassification::Success) {
            throw WebhookException::unexpectedClassification($classification->value);
        }

        // A payload-specific failure is this message's problem, not the endpoint's.
        if ($classification === ErrorClassification::NonTransientPayload) {
            return $this->fetchRow($webhookId)->state ?? EndpointState::Healthy;
        }

        $change = $this->transition($webhookId, fn (HealthRow $row): ?HealthRow => $this->decideFailure($row, $classification, $attempt));
        $this->emit($webhookId, $change, cause: match ($classification) {
            ErrorClassification::NonTransientEndpoint => SuspensionCause::Gone,
            ErrorClassification::NonTransientAuth => SuspensionCause::AuthStreak,
            default => SuspensionCause::ScheduleExhausted,
        });

        return $change->to->state ?? EndpointState::Healthy;
    }

    public function tick(): void
    {
        $this->shiftSuspensionClocks(appId: null);
        $this->runDueReleases();
        $this->retireSuspendedPastBound();

        foreach ($this->outboxStore->findSuspendedWebhookIdsWithClaimableRows() as $webhookId) {
            $this->outboxStore->cancelSurplusInFlightRows($webhookId);
        }

        foreach ($this->outboxStore->findWebhookIdsWithStrandedHolds() as $webhookId) {
            $this->outboxStore->resumeDeliveriesForWebhook($webhookId);
        }

        foreach ($this->outboxStore->findDisabledWebhookIdsWithHeldRows() as $webhookId) {
            $this->outboxStore->dropBacklogForWebhook($webhookId);
        }

        $this->outboxStore->cancelOrphanedHeldRows();
    }

    /**
     * Marks the moment from which suspension time stops counting; `updated_at` is the cursor.
     */
    public function pauseSuspensionClockForApp(string $appId): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_health wh
             JOIN webhook w ON w.id = wh.webhook_id
             SET wh.updated_at = :now
             WHERE w.app_id = :appId AND wh.endpoint_state = :suspended',
            [
                'now' => $this->now(),
                'appId' => Uuid::fromHexToBytes($appId),
                'suspended' => EndpointState::Suspended->value,
            ]
        );
    }

    public function resumeSuspensionClockForApp(string $appId): void
    {
        $this->shiftSuspensionClocks($appId);
    }

    public function reactivate(string $webhookId, WebhookActivationTrigger $trigger): int
    {
        $change = $this->transition($webhookId, function (HealthRow $row) use ($trigger): HealthRow {
            // Writing the row unchanged repairs the legacy mirror; a HEALTHY result also resumes stranded holds.
            // A refused recovery repairs the mirror but releases nothing.
            if ($row->state === EndpointState::Healthy || !$this->reactivationAllows($trigger, $row)) {
                return clone $row;
            }

            return $row->toHealthy(keepStreaks: false);
        });
        $this->emit($webhookId, $change, trigger: $trigger);

        return $change !== null && $change->changedState() ? 1 : 0;
    }

    public function reactivateForApp(string $appId): void
    {
        /** @var list<string> $webhookIds */
        $webhookIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(wh.webhook_id)) FROM webhook_health wh
             JOIN webhook w ON w.id = wh.webhook_id
             WHERE w.app_id = :appId AND wh.endpoint_state <> :healthy',
            [
                'appId' => Uuid::fromHexToBytes($appId),
                'healthy' => EndpointState::Healthy->value,
            ]
        );

        foreach ($webhookIds as $webhookId) {
            $this->reactivate($webhookId, WebhookActivationTrigger::AppReset);
        }
    }

    public function disableByOperatorOnActiveFlip(string $webhookId): void
    {
        // A mirrored active=false write carries intent only where it changes the value.
        $this->disable($webhookId, fromAnyState: false);
    }

    /**
     * The dedicated action carries operator intent in every state, and turns an escalation disable
     * into an operator kill.
     */
    public function disableByOperator(string $webhookId): int
    {
        return $this->disable($webhookId, fromAnyState: true);
    }

    /**
     * @deprecated tag:v6.8.0 - Pre-rework `error_count` failure handling. Runs only with WEBHOOKS_REWORK
     * off and is removed together with the `webhook.active`/`error_count` columns.
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
     * @deprecated tag:v6.8.0 - Pre-rework `error_count` reset. Runs only with WEBHOOKS_REWORK off and is
     * removed together with the legacy columns.
     */
    public function resetErrorCount(string $webhookId): void
    {
        $this->connection->update('webhook', ['error_count' => 0], ['id' => Uuid::fromHexToBytes($webhookId)]);
    }

    /**
     * A 2xx climbs exactly one state: SUSPENDED → DEGRADED keeps the incident clock and restarts the
     * ladder at tier 0; DEGRADED → HEALTHY ends the incident and resumes the held backlog.
     */
    private function decideSuccess(HealthRow $row): ?HealthRow
    {
        if ($row->state === EndpointState::Disabled) {
            return null;
        }

        if ($row->state !== EndpointState::Suspended) {
            return $row->toHealthy(keepStreaks: false);
        }

        $next = clone $row;
        $next->state = EndpointState::Degraded;
        $next->consecutiveTransientFailures = 0;
        $next->consecutiveNonTransientFailures = 0;
        $next->degradedCycleCount = 0;
        $next->cooldownUntil = $this->cooldownAt(0);

        return $next;
    }

    private function decideFailure(HealthRow $row, ErrorClassification $classification, int $attempt): ?HealthRow
    {
        if ($row->state === EndpointState::Disabled) {
            return null;
        }

        $next = clone $row;

        // The auth streak is independent of the trial ladder and counts on every delivery.
        if ($classification === ErrorClassification::NonTransientAuth) {
            ++$next->consecutiveNonTransientFailures;
        }
        $trips = $classification === ErrorClassification::NonTransientEndpoint
            || ($classification === ErrorClassification::NonTransientAuth && $next->consecutiveNonTransientFailures >= $this->config->nonTransientThreshold);

        if ($row->state === EndpointState::Healthy) {
            if ($trips) {
                return $this->suspend($next, entryTier: 0);
            }

            if ($classification->isTransient()) {
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
            }

            return $next;
        }

        if ($trips && $row->state === EndpointState::Degraded) {
            return $this->suspend($next, entryTier: 0);
        }

        // Only a released trial moves the ladder; a result landing inside the cooldown is a straggler.
        if (!$row->cooldownElapsed($this->now())) {
            return $classification === ErrorClassification::NonTransientAuth ? $next : null;
        }

        $nextTier = $row->degradedCycleCount + 1;
        if ($row->state === EndpointState::Degraded && $nextTier > $this->topTier()) {
            // The DEGRADED budget is the schedule's length; an exhausted ladder keeps backing off at the top tier.
            return $this->suspend($next, entryTier: $this->topTier());
        }

        $next->degradedCycleCount = min($nextTier, $this->topTier());
        $next->cooldownUntil = $this->cooldownAt($next->degradedCycleCount);

        return $next;
    }

    /**
     * `suspended_since` is written once per incident and survives a re-suspension.
     */
    private function suspend(HealthRow $next, int $entryTier): HealthRow
    {
        $next->state = EndpointState::Suspended;
        $next->suspendedSince ??= $this->now();
        $next->degradedCycleCount = $entryTier;
        $next->cooldownUntil = $this->cooldownAt($entryTier);

        return $next;
    }

    /**
     * Keeps operator kills out of automated recovery, and treats a mirrored active=true on a DEGRADED
     * webhook (whose mirror is already true) as an echo rather than intent.
     */
    private function reactivationAllows(WebhookActivationTrigger $trigger, HealthRow $row): bool
    {
        return match ($trigger) {
            WebhookActivationTrigger::Manual => $row->state === EndpointState::Suspended || $row->state === EndpointState::Disabled,
            WebhookActivationTrigger::AppReset,
            WebhookActivationTrigger::AppReactivateApi => !($row->state === EndpointState::Disabled && $row->disabledOrigin === DisabledOrigin::Operator),
            WebhookActivationTrigger::Trial,
            WebhookActivationTrigger::Idle => false,
        };
    }

    private function disable(string $webhookId, bool $fromAnyState): int
    {
        $change = $this->transition($webhookId, function (HealthRow $row) use ($fromAnyState): ?HealthRow {
            if ($row->state === EndpointState::Disabled) {
                if (!$fromAnyState || $row->disabledOrigin === DisabledOrigin::Operator) {
                    return null;
                }

                $next = clone $row;
                $next->disabledOrigin = DisabledOrigin::Operator;

                return $next;
            }

            // An echoed active=false on a SUSPENDED webhook (mirror already false) carries no intent.
            if (!$fromAnyState && $row->state === EndpointState::Suspended) {
                return null;
            }

            return $row->toDisabled(DisabledOrigin::Operator, $this->now());
        });

        if ($change === null || !$change->changedState()) {
            return 0;
        }

        $this->emit($webhookId, $change);
        $this->logger->warning('Webhook endpoint disabled by operator', ['webhookId' => $webhookId]);

        return 1;
    }

    /**
     * Admits one natural-traffic trial. The re-arm UPDATE succeeds only while the cooldown has elapsed, the
     * ladder is where the caller saw it, and no row of this webhook is held or in flight — exactly one
     * caller per burst is admitted, without a row lock.
     */
    private function admitIncidentTrial(string $webhookId, HealthRow $row): WebhookDispatchDecision
    {
        // A held row means the tick releases the trial; nothing else needs to reach the health row.
        if ($this->outboxStore->hasHeldRows($webhookId)) {
            return WebhookDispatchDecision::Skip;
        }

        $tier = min($row->degradedCycleCount + 1, $this->topTier());
        $now = $this->now();

        $admitted = (int) $this->connection->executeStatement(
            'UPDATE webhook_health
             SET degraded_cycle_count = :tier, cooldown_until = :cooldown, updated_at = :now
             WHERE webhook_id = :id
               AND endpoint_state = :state
               AND degraded_cycle_count = :seenTier
               AND (cooldown_until IS NULL OR cooldown_until <= :now)
               AND NOT EXISTS (
                   SELECT 1 FROM webhook_delivery d
                   WHERE d.webhook_id = :id AND d.delivery_status IN (:paused, :queued, :pendingRetry, :running)
               )',
            [
                'tier' => $tier,
                'cooldown' => $this->cooldownAt($tier),
                'now' => $now,
                'id' => Uuid::fromHexToBytes($webhookId),
                'state' => $row->state->value,
                'seenTier' => $row->degradedCycleCount,
                'paused' => WebhookEventLogDefinition::STATUS_PAUSED,
                'queued' => WebhookEventLogDefinition::STATUS_QUEUED,
                'pendingRetry' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                'running' => WebhookEventLogDefinition::STATUS_RUNNING,
            ]
        );

        return $admitted > 0 ? WebhookDispatchDecision::Deliver : WebhookDispatchDecision::Skip;
    }

    private function runDueReleases(): void
    {
        $now = $this->now();

        /** @var list<string> $candidates */
        $candidates = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(wh.webhook_id))
             FROM webhook_health wh
             LEFT JOIN webhook w ON w.id = wh.webhook_id
             LEFT JOIN app a ON a.id = w.app_id
             WHERE (wh.cooldown_until IS NULL OR wh.cooldown_until <= :now)
               AND (
                    wh.endpoint_state = :degraded
                    OR (wh.endpoint_state = :suspended AND (a.id IS NULL OR a.active = 1))
               )',
            [
                'now' => $now,
                'degraded' => EndpointState::Degraded->value,
                'suspended' => EndpointState::Suspended->value,
            ]
        );

        foreach ($candidates as $webhookId) {
            $change = $this->transition($webhookId, function (HealthRow $row) use ($webhookId, $now): ?HealthRow {
                if (!\in_array($row->state, [EndpointState::Degraded, EndpointState::Suspended], true) || !$row->cooldownElapsed($now)) {
                    return null;
                }

                // One release at a time: a trial still in flight, or one released now, ends this tick's duty.
                if ($this->outboxStore->hasClaimableOrRunningRows($webhookId) || $this->outboxStore->releaseOneTrial($webhookId) !== null) {
                    return null;
                }

                // SUSPENDED never idle-promotes; the gate admits its next natural event instead.
                if ($row->state === EndpointState::Suspended) {
                    return null;
                }

                // Nothing held, nothing in flight: idle promotion. Unproven streaks stay.
                return $row->toHealthy(keepStreaks: true);
            });
            $this->emit($webhookId, $change, trigger: WebhookActivationTrigger::Idle);
        }
    }

    private function retireSuspendedPastBound(): void
    {
        $cutoff = $this->clock->now()
            ->modify(\sprintf('-%d days', $this->config->maxSuspendedDays))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        /** @var list<string> $candidates */
        $candidates = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(wh.webhook_id))
             FROM webhook_health wh
             LEFT JOIN webhook w ON w.id = wh.webhook_id
             LEFT JOIN app a ON a.id = w.app_id
             WHERE wh.endpoint_state = :suspended
               AND wh.suspended_since IS NOT NULL AND wh.suspended_since <= :cutoff
               AND (a.id IS NULL OR a.active = 1)',
            ['suspended' => EndpointState::Suspended->value, 'cutoff' => $cutoff]
        );

        foreach ($candidates as $webhookId) {
            $change = $this->transition($webhookId, function (HealthRow $row) use ($cutoff): ?HealthRow {
                if ($row->state !== EndpointState::Suspended || $row->suspendedSince === null || $row->suspendedSince > $cutoff) {
                    return null;
                }

                return $row->toDisabled(DisabledOrigin::Escalation, $this->now());
            });

            if ($change === null || !$change->changedState()) {
                continue;
            }

            $this->emit($webhookId, $change);
            $this->logger->warning('Webhook endpoint disabled after exceeding the suspension bound', [
                'webhookId' => $webhookId,
                'maxSuspendedDays' => $this->config->maxSuspendedDays,
            ]);
        }
    }

    /**
     * Moves suspension clocks forward by the time since the pause cursor, so deactivated time never counts
     * toward the retirement bound. Scoped to one app on reactivation; to every inactive app on the tick.
     */
    private function shiftSuspensionClocks(?string $appId): void
    {
        $this->connection->executeStatement(
            \sprintf(
                'UPDATE webhook_health wh
                 JOIN webhook w ON w.id = wh.webhook_id
                 LEFT JOIN app a ON a.id = w.app_id
                 JOIN (SELECT webhook_id, updated_at AS cursor_at FROM webhook_health
                       WHERE endpoint_state = :suspended AND updated_at IS NOT NULL) snap
                   ON snap.webhook_id = wh.webhook_id
                 SET wh.suspended_since = TIMESTAMPADD(MICROSECOND, TIMESTAMPDIFF(MICROSECOND, snap.cursor_at, :now), wh.suspended_since),
                     wh.updated_at = :now
                 WHERE %s
                   AND wh.endpoint_state = :suspended
                   AND wh.suspended_since IS NOT NULL
                   AND snap.cursor_at < :now',
                $appId === null ? 'a.active = 0' : 'w.app_id = :appId'
            ),
            [
                ...($appId === null ? [] : ['appId' => Uuid::fromHexToBytes($appId)]),
                'now' => $this->now(),
                'suspended' => EndpointState::Suspended->value,
            ]
        );
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
            $this->mirrorBcColumns($id);

            // Held rows re-enter under the same lock, so a concurrent gate never sees HEALTHY with a paused backlog.
            if ($next->state === EndpointState::Healthy) {
                $this->outboxStore->resumeDeliveriesForWebhook($webhookId);
            }

            return new HealthChange($row, $next);
        });

        if ($change === null || !$change->changedState()) {
            return $change;
        }

        if ($change->to->state === EndpointState::Disabled) {
            $this->outboxStore->dropBacklogForWebhook($webhookId);
        } elseif ($this->entersHold($change->from->state, $change->to->state)) {
            $this->outboxStore->pauseDeliveriesForWebhook($webhookId);
        }

        return $change;
    }

    /**
     * The claimable backlog is held when an incident starts or deepens; SUSPENDED → DEGRADED keeps it held as is.
     */
    private function entersHold(EndpointState $from, EndpointState $to): bool
    {
        return \in_array($to, [EndpointState::Degraded, EndpointState::Suspended], true)
            && $from !== EndpointState::Suspended;
    }

    /**
     * Every state entry emits one best-effort lifecycle event after the transition has committed.
     */
    private function emit(string $webhookId, ?HealthChange $change, ?WebhookActivationTrigger $trigger = null, ?SuspensionCause $cause = null): void
    {
        if ($change === null || !$change->changedState()) {
            return;
        }

        $ref = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(app_id)) AS app_id, name, event_name FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );
        $appId = \is_array($ref) && \is_string($ref['app_id']) ? $ref['app_id'] : null;
        $name = \is_array($ref) && \is_string($ref['name']) ? $ref['name'] : null;
        $eventName = \is_array($ref) && \is_string($ref['event_name']) ? $ref['event_name'] : null;

        $from = $change->from;
        $to = $change->to;
        $now = $this->clock->now();

        if ($to->state === EndpointState::Healthy) {
            \assert($trigger !== null);
            $event = new WebhookActivatedEvent(
                $webhookId,
                $appId,
                $from->state,
                $trigger,
                $name,
                $eventName,
                $now,
                $from->suspendedSince === null ? null : new \DateTimeImmutable($from->suspendedSince),
            );
        } elseif ($to->state === EndpointState::Degraded) {
            $event = new WebhookDegradedEvent($webhookId, $appId, $from->state, $name, $eventName, $now);
        } elseif ($to->state === EndpointState::Suspended) {
            \assert($cause !== null && $to->suspendedSince !== null);
            $event = new WebhookSuspendedEvent($webhookId, $appId, $from->state, new \DateTimeImmutable($to->suspendedSince), $cause, $name, $eventName, $now);
        } else {
            \assert($to->disabledOrigin !== null);
            $event = new WebhookDisabledEvent($webhookId, $appId, $from->state, $to->disabledOrigin, $name, $eventName, $now);
        }

        try {
            $this->eventDispatcher->dispatch($event);
        } catch (\Throwable $e) {
            // Lifecycle events are advisory; the committed transition is the truth.
            $this->logger->warning('Webhook lifecycle event listener failed', [
                'event' => $event::class,
                'exception' => $e::class,
            ]);
        }
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
                'origin' => $row->disabledOrigin?->value,
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

    private function cooldownAt(int $tier): string
    {
        $seconds = $this->config->cooldownScheduleSeconds[min($tier, $this->topTier())];

        return $this->clock->now()
            ->modify(\sprintf('+%d seconds', $seconds))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function now(): string
    {
        return $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
