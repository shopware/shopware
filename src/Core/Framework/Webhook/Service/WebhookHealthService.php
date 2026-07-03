<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Event\WebhookDegradedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointHealth;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\Health\SuspensionCause;
use Shopware\Core\Framework\Webhook\Health\WebhookDispatchDecision;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;

/**
 * Owns webhook health end to end (#16565): the legacy shared-counter disable under `WEBHOOKS_REWORK`
 * off (the {@see recordLegacyFailure}/{@see resetErrorCount} path), and the per-webhook circuit
 * breaker under flag-on (the {@see EndpointHealth} + {@see EndpointLifecycle} state machine on the
 * internal `webhook_health` table).
 *
 * Every flag-on transition reads the health row `FOR UPDATE` inside a retryable transaction and
 * writes guarded on the state it read — a lost race matches zero rows and the side effects
 * (pause/resume/mirror) gate on the transition actually happening, so two writers never both
 * transition. The **BC mirror** ({@see mirrorBcColumns}) then derives the legacy
 * `webhook.active`/`error_count` columns from the *current* health row in one JOINed write — never
 * from a caller's stale assumption — so a late mirror can't reinstate `active = 1` on an endpoint a
 * concurrent transition just suspended. It is per-webhook (no `RelatedWebhooks` sibling propagation;
 * that cross-webhook blast radius is exactly what the rework removes).
 *
 * Lifecycle events ({@see WebhookActivatedEvent} & friends) are emitted post-commit and best-effort:
 * the `webhook_health` row is the truth and a listener failure never affects the transition.
 *
 * @internal
 */
#[Package('framework')]
class WebhookHealthService implements EndpointHealth, EndpointLifecycle
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RelatedWebhooks $relatedWebhooks,
        private readonly WebhookOutboxStore $outboxStore,
        private readonly HealthConfig $config,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function gateFor(string $webhookId): WebhookDispatchDecision
    {
        return match ($this->currentState($webhookId)) {
            EndpointState::Healthy => WebhookDispatchDecision::Deliver,
            EndpointState::Degraded => WebhookDispatchDecision::Hold,
            // SUSPENDED holds its pre-suspension backlog and sheds new events (no row, no write) —
            // except that with nothing held and nothing in flight, an elapsed cooldown admits the
            // next natural event as the half-open trial. DISABLED never recovers on traffic.
            EndpointState::Suspended => $this->admitSuspendedTrial($webhookId),
            EndpointState::Disabled => WebhookDispatchDecision::Skip,
        };
    }

    public function recordSuccess(string $webhookId): void
    {
        // A 2xx climbs exactly one state: SUSPENDED → DEGRADED (ladder reset to tier 0,
        // suspended_since preserved — HEALTHY is earned through the same ladder), or
        // DEGRADED → HEALTHY (full reset + resume of the held backlog, age-filtered).
        if ($this->deEscalateSuspendedToDegraded($webhookId)) {
            return;
        }

        if ($this->promoteDegradedToHealthy($webhookId, WebhookActivationTrigger::Trial)) {
            return;
        }

        // HEALTHY with partial streaks: any 2xx resets both failure counters, so unrelated
        // failures don't accumulate across outages.
        $cleared = (int) $this->connection->executeStatement(
            'UPDATE webhook_health
             SET consecutive_transient_failures = 0, consecutive_non_transient_failures = 0, updated_at = :now
             WHERE webhook_id = :id AND endpoint_state = :healthy
               AND (consecutive_transient_failures > 0 OR consecutive_non_transient_failures > 0)',
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

        // BC mirror reconcile: trunk reset webhook.error_count on every success, and the generic
        // /api/webhook surface reads (and filters/sorts on) that column. A HEALTHY endpoint mirrors
        // error_count = 0, so a healthy webhook whose legacy counter drifted above 0 — including one
        // with no health row yet (fail-open HEALTHY) that accrued a legacy count before the flag — must
        // be reconciled here, since mirrorBcColumns only fires on a state transition and its inner JOIN
        // can't reach a no-row webhook. Per-webhook only (no RelatedWebhooks sibling propagation — the
        // blast-radius bug this rework fixes). `active` is deliberately untouched: trunk's success path
        // never reactivated, and the health-state guard keeps a concurrently SUSPENDED/DISABLED row safe.
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
        match ($classification) {
            // A successful delivery is recorded via recordSuccess; reaching here is a caller bug —
            // crash loudly instead of mis-recording it as a failure.
            ErrorClassification::Success => throw WebhookException::unexpectedClassification($classification->value),
            // Payload/message-specific (400, other unlisted 4xx): never touches endpoint health, and
            // never consumes a trial — no cooldown was advanced, so the next tick releases the
            // next-oldest held row.
            ErrorClassification::NonTransientPayload => null,
            // Auth rejection: feeds the non-transient streak; suspends at the threshold.
            ErrorClassification::NonTransientAuth => $this->recordAuthFailure($webhookId),
            // 410 Gone: the endpoint's explicit retirement signal — suspends immediately.
            ErrorClassification::NonTransientEndpoint => $this->recordGoneFailure($webhookId),
            ErrorClassification::TransientNetwork,
            ErrorClassification::TransientServer,
            ErrorClassification::TransientRateLimit,
            ErrorClassification::TransientRedirect => $this->recordTransientFailure($webhookId, $attempt),
        };

        // Resulting state drives the in-flight row's placement on the result side (re-hold / fail / retry).
        return $this->currentState($webhookId);
    }

    /**
     * One scheduled tick over the five clocked duties (ADR §Half-open recovery): trial releases for
     * both non-HEALTHY states, idle promotion, the 7-day retirement, crash-leftover cleanup, and
     * stale-hold healing — plus the suspension-clock pause for deactivated apps. Each duty is a
     * cheap indexed check; every per-webhook action runs in its own short transaction.
     */
    public function tick(): int
    {
        $this->shiftPausedSuspensionClocks();

        return $this->runDueReleases()
            + $this->retireSuspendedPastBound()
            + $this->cancelSurplusSuspendedInFlight()
            + $this->healStrandedHolds();
    }

    /**
     * Duties 1 + 2: per DEGRADED/SUSPENDED webhook with an elapsed cooldown, release the oldest held
     * row as the trial — or, for DEGRADED with nothing held and nothing in flight, idle-promote.
     * SUSPENDED never idle-promotes (the gate admits the next natural event as its trial instead),
     * and SUSPENDED webhooks of deactivated apps get no trials at all — their events are
     * eligibility-filtered before the gate, so their suspension clock is paused, not running.
     */
    private function runDueReleases(): int
    {
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
                'now' => $this->now(),
                'degraded' => EndpointState::Degraded->value,
                'suspended' => EndpointState::Suspended->value,
            ]
        );

        $acted = 0;
        foreach ($candidates as $webhookId) {
            $acted += RetryableTransaction::retryable($this->connection, function () use ($webhookId): int {
                // Serialise concurrent ticks on this webhook: hold the health row FOR UPDATE across
                // the in-flight check, release, and idle promotion. Without it two ticks can both
                // pass the in-flight check and double-release, or one can idle-promote while the
                // other's just-released trial is mid-flight. Re-confirm the state under the lock.
                $row = $this->lockHealthRow($webhookId);
                if ($row === null) {
                    return 0;
                }
                $state = EndpointState::from((string) $row['endpoint_state']);
                if ($state !== EndpointState::Degraded && $state !== EndpointState::Suspended) {
                    return 0;
                }
                if ($row['cooldown_until'] !== null && (string) $row['cooldown_until'] > $this->now()) {
                    return 0;
                }

                // A prior release still in flight (claimable or running) → no-op: the ladder advances
                // on its result, not the wall clock, so worker lag can't march an endpoint to
                // SUSPENDED without delivery evidence.
                if ($this->hasInFlightRow($webhookId)) {
                    return 0;
                }

                // A held row → release exactly one as the trial; its result drives the ladder.
                // Releasing itself never counts: the cooldown stays elapsed, so the result lands
                // counted (and re-arms the next tier).
                if ($this->outboxStore->releaseOneTrial($webhookId) !== null) {
                    return 1;
                }

                if ($state === EndpointState::Suspended) {
                    // Nothing held: the gate admits the next natural event as the trial.
                    return 0;
                }

                // True idle (nothing held, nothing in flight) → promote to HEALTHY. A guarded "stay"
                // would wedge an idle DEGRADED webhook forever (it can only advance via a trial result).
                return $this->promoteDegradedToHealthy($webhookId, WebhookActivationTrigger::Idle) ? 1 : 0;
            });
        }

        return $acted;
    }

    /**
     * Duty 3: SUSPENDED webhooks held past `suspended_since + max_suspended_days` retire to DISABLED
     * (origin escalation) — time-based, not traffic-based, so a dead endpoint still retires. Skips
     * webhooks of deactivated apps: their clock is paused ({@see shiftPausedSuspensionClocks}), and
     * retiring them at the first tick after reactivation would punish time without a recovery path.
     */
    private function retireSuspendedPastBound(): int
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

        $retired = 0;
        foreach ($candidates as $webhookId) {
            $event = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $cutoff): ?WebhookDisabledEvent {
                $id = Uuid::fromHexToBytes($webhookId);
                // SKIP LOCKED steps past a candidate a concurrent tick or trial is touching; the
                // guarded UPDATE makes a trial that recovered it to DEGRADED a zero-row no-op.
                $locked = $this->connection->fetchOne(
                    'SELECT 1 FROM webhook_health
                     WHERE webhook_id = :id AND endpoint_state = :suspended AND suspended_since <= :cutoff
                     FOR UPDATE SKIP LOCKED',
                    ['id' => $id, 'suspended' => EndpointState::Suspended->value, 'cutoff' => $cutoff]
                );
                if ($locked === false) {
                    return null;
                }

                $disabledRows = (int) $this->connection->executeStatement(
                    'UPDATE webhook_health
                     SET endpoint_state = :disabled, disabled_since = :now, disabled_origin = :origin,
                         cooldown_until = NULL, updated_at = :now
                     WHERE webhook_id = :id AND endpoint_state = :suspended',
                    [
                        'disabled' => EndpointState::Disabled->value,
                        'now' => $this->now(),
                        'origin' => DisabledOrigin::Escalation->value,
                        'id' => $id,
                        'suspended' => EndpointState::Suspended->value,
                    ]
                );
                if ($disabledRows === 0) {
                    return null;
                }
                $this->mirrorBcColumns($webhookId);

                return new WebhookDisabledEvent($webhookId, $this->appIdOf($webhookId), EndpointState::Suspended, DisabledOrigin::Escalation);
            });

            if ($event === null) {
                continue;
            }

            // DISABLED cancels everything still undelivered — queued, held, and mid-flight alike.
            $this->outboxStore->dropBacklogForWebhook($webhookId, WebhookOutboxStore::DROP_REASON_DISABLED);
            $this->dispatchBestEffort($event);
            $this->logger->warning('Webhook endpoint disabled after exceeding the suspension bound', [
                'webhookId' => $webhookId,
                'maxSuspendedDays' => $this->config->maxSuspendedDays,
            ]);
            ++$retired;
        }

        return $retired;
    }

    /**
     * Duty 4: enforce "only the one deliberately released row may be in flight" on SUSPENDED
     * webhooks — crash-recovery can resurrect pre-suspension deliveries as claimable rows.
     */
    private function cancelSurplusSuspendedInFlight(): int
    {
        /** @var list<string> $candidates */
        $candidates = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(d.webhook_id))
             FROM webhook_delivery d
             JOIN webhook_health wh ON wh.webhook_id = d.webhook_id
             WHERE wh.endpoint_state = :suspended AND d.delivery_status IN (:queued, :pendingRetry)',
            [
                'suspended' => EndpointState::Suspended->value,
                'queued' => 'queued',
                'pendingRetry' => 'pending_retry',
            ]
        );

        $acted = 0;
        foreach ($candidates as $webhookId) {
            $acted += $this->outboxStore->cancelSurplusInFlightRows($webhookId) > 0 ? 1 : 0;
        }

        return $acted;
    }

    /**
     * Duty 5: resume `paused` rows stranded on a HEALTHY webhook by a Hold decision that raced a
     * recovery (the gate persists its decision verbatim; this is the absorption side of that race).
     */
    private function healStrandedHolds(): int
    {
        $acted = 0;
        foreach ($this->outboxStore->findWebhookIdsWithStrandedHolds() as $webhookId) {
            $this->outboxStore->resumeDeliveriesForWebhook($webhookId);
            ++$acted;
        }

        return $acted;
    }

    /**
     * The suspension-clock pause for deactivated apps (ADR §SUSPENDED): each tick moves
     * `suspended_since` forward by the time elapsed since the row's last write, so only time with a
     * live recovery path counts toward the 7-day bound — net effect identical to rebasing the clock
     * at reactivation, without storing the deactivation timestamp anywhere. `updated_at` is the
     * shift cursor; {@see pauseSuspensionClockForApp} initialises it at deactivation. MySQL applies
     * SET clauses left to right, so `suspended_since` reads the pre-shift cursor.
     */
    private function shiftPausedSuspensionClocks(): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_health wh
             JOIN webhook w ON w.id = wh.webhook_id
             JOIN app a ON a.id = w.app_id
             SET wh.suspended_since = TIMESTAMPADD(MICROSECOND, TIMESTAMPDIFF(MICROSECOND, wh.updated_at, :now), wh.suspended_since),
                 wh.updated_at = :now
             WHERE a.active = 0
               AND wh.endpoint_state = :suspended
               AND wh.suspended_since IS NOT NULL
               AND wh.updated_at IS NOT NULL AND wh.updated_at < :now',
            ['now' => $this->now(), 'suspended' => EndpointState::Suspended->value]
        );
    }

    public function pauseSuspensionClockForApp(string $appId): void
    {
        // Initialise the shift cursor (updated_at) at the deactivation moment, so the first tick's
        // shift covers exactly the deactivated time — not the gap back to the last health write.
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

    public function reactivate(string $webhookId, WebhookActivationTrigger $trigger): int
    {
        return $this->reactivateFrom($webhookId, $trigger, null);
    }

    public function reactivateOnActiveFlip(string $webhookId): int
    {
        // The admin `PATCH active = true` gesture. Intent is read only from a write that actually
        // flips the mirrored value (ADR §Admin API backwards-compat): SUSPENDED/DISABLED mirror
        // active = false, so the write is a genuine flip there — including an operator-disabled
        // webhook, whose recovery is exactly this gesture. On HEALTHY/DEGRADED the mirrored value is
        // already true, so the write is an echo and must not feed back into the state machine (a
        // full-entity round-trip of a DEGRADED webhook must not reset it). The idempotent heal
        // inside still runs for HEALTHY rows (drifted mirror, stranded holds).
        return $this->reactivateFrom($webhookId, WebhookActivationTrigger::Manual, [EndpointState::Suspended, EndpointState::Disabled]);
    }

    /**
     * @param list<EndpointState>|null $onlyFrom restricts which states may transition; null = any non-HEALTHY
     */
    private function reactivateFrom(string $webhookId, WebhookActivationTrigger $trigger, ?array $onlyFrom): int
    {
        $event = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $trigger, $onlyFrom): ?WebhookActivatedEvent {
            // Serialise with concurrent transitions via the webhook row lock; bail if the webhook is gone.
            $exists = (bool) $this->connection->fetchOne(
                'SELECT 1 FROM webhook WHERE id = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($webhookId)]
            );
            if (!$exists) {
                return null;
            }

            $row = $this->connection->fetchAssociative(
                'SELECT endpoint_state, suspended_since FROM webhook_health WHERE webhook_id = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($webhookId)]
            );

            if (!\is_array($row)) {
                // Fail-open HEALTHY without a health row: nothing to transition, but the operator
                // gesture still repairs a drifted legacy mirror (a flag-off auto-disable left
                // active = 0 while the health model reads the webhook as healthy).
                $this->connection->executeStatement(
                    'UPDATE webhook SET active = 1, error_count = 0 WHERE id = :id',
                    ['id' => Uuid::fromHexToBytes($webhookId)]
                );
                $this->outboxStore->resumeDeliveriesForWebhook($webhookId);

                return null;
            }

            $fromState = EndpointState::from((string) $row['endpoint_state']);

            $transitioned = $fromState !== EndpointState::Healthy
                && ($onlyFrom === null || \in_array($fromState, $onlyFrom, true))
                && $this->resetToHealthy($webhookId, keepStreaks: false);

            // Idempotent heal on a real transition or an already-HEALTHY webhook: the mirror repairs
            // drifted legacy columns and the resume releases a crash-stranded held backlog — both
            // no-ops when there is nothing to repair. Inside the transaction, so a crash can't
            // commit the transition without them (a HEALTHY row that WebhookLoader's active=1 filter
            // excludes would be an unrecoverable zombie). A REFUSED echo (e.g. PATCH active=true on
            // DEGRADED) must heal nothing: resuming would release a deliberately held backlog onto
            // an endpoint that is still breaking.
            if (!$transitioned && $fromState !== EndpointState::Healthy) {
                // A refused gesture still re-derives the BC mirror (state untouched, nothing
                // resumed): the app persister force-writes active = 1 on every app update, and a
                // refused recovery must not leave an operator-killed webhook reading active.
                $this->mirrorBcColumns($webhookId);

                return null;
            }

            $this->mirrorBcColumns($webhookId);
            $this->outboxStore->resumeDeliveriesForWebhook($webhookId);

            if (!$transitioned) {
                return null;
            }

            $ref = $this->webhookRefOf($webhookId);

            return new WebhookActivatedEvent(
                $webhookId,
                $ref['appId'],
                $fromState,
                $trigger,
                $ref['name'],
                $ref['eventName'],
                $this->clock->now(),
                $this->toDateTime($row['suspended_since']),
            );
        });

        if ($event === null) {
            return 0;
        }

        $this->dispatchBestEffort($event);

        return 1;
    }

    public function reactivateForApp(string $appId): int
    {
        // App install/update is a deliberate config refresh — a clean slate that replaces exactly
        // what usually broke the endpoint. Reset every non-HEALTHY webhook of the app via the
        // per-webhook reactivate (resume, mirror, one WebhookActivatedEvent each) — EXCEPT an
        // operator-disabled one: a merchant's explicit kill survives a routine app update; only the
        // operator gesture reverses it.
        /** @var list<string> $webhookIds */
        $webhookIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(wh.webhook_id)) FROM webhook_health wh
             JOIN webhook w ON w.id = wh.webhook_id
             WHERE w.app_id = :appId AND wh.endpoint_state <> :healthy
               AND NOT (wh.endpoint_state = :disabled AND wh.disabled_origin = :operator)',
            [
                'appId' => Uuid::fromHexToBytes($appId),
                'healthy' => EndpointState::Healthy->value,
                'disabled' => EndpointState::Disabled->value,
                'operator' => DisabledOrigin::Operator->value,
            ]
        );

        $reset = 0;
        foreach ($webhookIds as $webhookId) {
            $reset += $this->reactivate($webhookId, WebhookActivationTrigger::AppReset);
        }

        // The excluded operator kills never reach reactivate(), but the app persister has just
        // force-written active = 1 on every webhook of the app — re-derive their BC mirror so the
        // merchant's kill keeps reading active = false.
        /** @var list<string> $operatorKilled */
        $operatorKilled = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(wh.webhook_id)) FROM webhook_health wh
             JOIN webhook w ON w.id = wh.webhook_id
             WHERE w.app_id = :appId AND wh.endpoint_state = :disabled AND wh.disabled_origin = :operator',
            [
                'appId' => Uuid::fromHexToBytes($appId),
                'disabled' => EndpointState::Disabled->value,
                'operator' => DisabledOrigin::Operator->value,
            ]
        );
        foreach ($operatorKilled as $webhookId) {
            $this->mirrorBcColumns($webhookId);
        }

        return $reset;
    }

    public function disableByOperator(string $webhookId): int
    {
        // The admin `PATCH active = false` gesture. Intent is read only from a write that actually
        // flips the mirrored value: HEALTHY/DEGRADED mirror active = true, so the write is a genuine
        // kill there; on SUSPENDED/DISABLED the mirrored value is already false — an echo (e.g. a
        // full-entity round-trip while suspended) that must not register as operator intent. The
        // fail-open INSERT is load-bearing: a never-failed webhook has no health row, yet its
        // already-enqueued deliveries keep delivering after active = false, and a late failure on
        // one would insert a fresh HEALTHY row and mirror active = 1 — resurrecting the endpoint the
        // operator just switched off.
        $event = RetryableTransaction::retryable($this->connection, function () use ($webhookId): ?WebhookDisabledEvent {
            $row = $this->lockHealthRow($webhookId);

            if ($row === null) {
                return $this->insertFreshDisabledRow($webhookId);
            }

            $fromState = EndpointState::from((string) $row['endpoint_state']);
            if ($fromState !== EndpointState::Healthy && $fromState !== EndpointState::Degraded) {
                return null;
            }

            $this->connection->executeStatement(
                'UPDATE webhook_health
                 SET endpoint_state = :disabled, disabled_since = :now, disabled_origin = :origin,
                     cooldown_until = NULL, updated_at = :now
                 WHERE webhook_id = :id AND endpoint_state = :from',
                [
                    'disabled' => EndpointState::Disabled->value,
                    'now' => $this->now(),
                    'origin' => DisabledOrigin::Operator->value,
                    'id' => Uuid::fromHexToBytes($webhookId),
                    'from' => $fromState->value,
                ]
            );
            $this->mirrorBcColumns($webhookId);

            return new WebhookDisabledEvent($webhookId, $this->appIdOf($webhookId), $fromState, DisabledOrigin::Operator);
        });

        if ($event === null) {
            return 0;
        }

        // DISABLED cancels everything still undelivered — an operator's kill also stops a backlog
        // that was still deliverable.
        $this->outboxStore->dropBacklogForWebhook($webhookId, WebhookOutboxStore::DROP_REASON_DISABLED);
        $this->dispatchBestEffort($event);
        $this->logger->warning('Webhook endpoint disabled by operator', ['webhookId' => $webhookId]);

        return 1;
    }

    /**
     * @deprecated tag:v6.8.0 - Pre-rework shared-counter failure handling; runs only under WEBHOOKS_REWORK
     * off and retires with the `webhook.active`/`error_count` columns. Renamed from `recordFailure` so the
     * per-delivery {@see EndpointHealth::recordFailure} can own that name under flag-on.
     *
     * Increments error_count and applies the strategy. No-op if the webhook is missing or inactive.
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

        $this->relatedWebhooks->updateRelated($webhookId, $params, Context::createDefaultContext());
    }

    /**
     * @deprecated tag:v6.8.0 - Pre-rework shared-counter reset; runs only under WEBHOOKS_REWORK off and
     * retires with the legacy columns. Under flag-on {@see recordSuccess} owns the per-webhook reset.
     */
    public function resetErrorCount(string $webhookId): void
    {
        $this->relatedWebhooks->updateRelated($webhookId, ['error_count' => 0], Context::createDefaultContext());
    }

    private function recordTransientFailure(string $webhookId, int $attempt): void
    {
        $current = $this->currentState($webhookId);

        // In DEGRADED/SUSPENDED every transient result is a released trial's (or a straggler's —
        // the ladder advance distinguishes them by the elapsed cooldown). DISABLED absorbs.
        if ($current === EndpointState::Degraded || $current === EndpointState::Suspended) {
            $this->advanceLadder($webhookId, $current);

            return;
        }

        if ($current === EndpointState::Disabled) {
            return;
        }

        // Per-delivery aggregation: only a delivery's first attempt counts toward the threshold, so one
        // delivery's retry ladder can't cross it alone (degraded_threshold == the per-delivery budget).
        if ($attempt > 1) {
            return;
        }

        $this->recordHealthyTransientFailure($webhookId);
    }

    private function recordHealthyTransientFailure(string $webhookId): void
    {
        $threshold = $this->config->degradedThreshold;
        $now = $this->now();
        $firstCooldown = $this->cooldownAt(0);
        $id = Uuid::fromHexToBytes($webhookId);

        $outcome = RetryableTransaction::retryable($this->connection, function () use ($id, $threshold, $now, $firstCooldown): string {
            // Atomic increment + conditional transition on an existing HEALTHY row. The crossing guard
            // includes `+ 1 >= threshold` so the row count alone tells us it transitioned (no post-read
            // race); `+ 1` reads the pre-update count because the increment is in the same SET clause.
            $apply = function () use ($id, $threshold, $now, $firstCooldown): ?string {
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
                        'id' => $id,
                        'threshold' => $threshold,
                    ]
                );
                if ($crossed > 0) {
                    return EndpointState::Degraded->value;
                }

                $incremented = (int) $this->connection->executeStatement(
                    'UPDATE webhook_health
                     SET consecutive_transient_failures = consecutive_transient_failures + 1, updated_at = :now
                     WHERE webhook_id = :id AND endpoint_state = :healthy
                       AND consecutive_transient_failures + 1 < :threshold',
                    [
                        'healthy' => EndpointState::Healthy->value,
                        'now' => $now,
                        'id' => $id,
                        'threshold' => $threshold,
                    ]
                );

                return $incremented > 0 ? EndpointState::Healthy->value : null;
            };

            $result = $apply();
            if ($result !== null) {
                return $result;
            }

            // No HEALTHY row matched: either the row already moved to a non-HEALTHY state concurrently
            // (absorb — not our transition), or none exists yet (fail-open first failure → insert it).
            if ((bool) $this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $id])) {
                return 'unchanged';
            }

            $crosses = $threshold <= 1;
            try {
                $this->connection->executeStatement(
                    'INSERT INTO webhook_health
                        (webhook_id, endpoint_state, consecutive_transient_failures, degraded_cycle_count, cooldown_until, created_at)
                     VALUES (:id, :state, 1, 0, :cooldown, :now)',
                    [
                        'id' => $id,
                        'state' => $crosses ? EndpointState::Degraded->value : EndpointState::Healthy->value,
                        'cooldown' => $crosses ? $firstCooldown : null,
                        'now' => $now,
                    ]
                );

                return $crosses ? EndpointState::Degraded->value : EndpointState::Healthy->value;
            } catch (UniqueConstraintViolationException) {
                // The row appeared concurrently — re-apply the guarded update so this failure is not
                // dropped (re-running the increment/crossing rather than absorbing it).
                return $apply() ?? 'unchanged';
            }
        });

        if ($outcome === EndpointState::Degraded->value) {
            // Hold the rest of the backlog for the ladder; the in-flight row is held by the result side.
            $this->outboxStore->pauseDeliveriesForWebhook($webhookId);
            $ref = $this->webhookRefOf($webhookId);
            $this->dispatchBestEffort(new WebhookDegradedEvent(
                $webhookId,
                $ref['appId'],
                EndpointState::Healthy,
                $ref['name'],
                $ref['eventName'],
                $this->clock->now(),
            ));
        }

        if ($outcome !== 'unchanged') {
            $this->mirrorBcColumns($webhookId);
        }
    }

    /**
     * A 401/403: advances the non-transient streak once per delivery (auth failures are terminal, so
     * each delivery produces at most one) and suspends at the threshold. Transient failures neither
     * advance nor reset the streak; only a 2xx resets it. On a DEGRADED or already-SUSPENDED webhook
     * the result is also a failed trial — the ladder climbs (straggler-guarded), the streak keeps
     * counting.
     */
    private function recordAuthFailure(string $webhookId): void
    {
        $suspension = RetryableTransaction::retryable($this->connection, function () use ($webhookId): ?WebhookSuspendedEvent {
            $row = $this->lockHealthRow($webhookId);

            if ($row === null) {
                // Fail-open first failure on a webhook without a health row.
                return $this->insertFreshRowForNonTransient($webhookId, nonTransientFailures: 1, suspend: $this->config->nonTransientThreshold <= 1, cause: SuspensionCause::AuthStreak);
            }

            $state = EndpointState::from((string) $row['endpoint_state']);

            if ($state === EndpointState::Suspended) {
                $this->advanceLadderLocked($webhookId, $row, EndpointState::Suspended, alsoCountAuthStreak: true);

                return null;
            }

            if ($state === EndpointState::Disabled) {
                return null;
            }

            $streak = (int) $row['consecutive_non_transient_failures'] + 1;
            if ($streak >= $this->config->nonTransientThreshold) {
                return $this->suspendLocked($webhookId, $row, $state, nonTransientFailures: $streak, cause: SuspensionCause::AuthStreak);
            }

            if ($state === EndpointState::Degraded) {
                // Below the threshold the auth failure still counts as a failed trial (ADR trial
                // results, rule 3): the ladder climbs and the cooldown re-arms — straggler-guarded,
                // exhaustion suspending — instead of leaving the cooldown elapsed, which would
                // release a fresh trial every tick.
                return $this->advanceLadderLocked($webhookId, $row, $state, alsoCountAuthStreak: true);
            }

            $this->connection->executeStatement(
                'UPDATE webhook_health
                 SET consecutive_non_transient_failures = :streak, updated_at = :now
                 WHERE webhook_id = :id',
                ['streak' => $streak, 'now' => $this->now(), 'id' => Uuid::fromHexToBytes($webhookId)]
            );
            $this->mirrorBcColumns($webhookId);

            return null;
        });

        $this->finishSuspension($webhookId, $suspension);
    }

    /**
     * A 410 Gone: suspends immediately — the endpoint's own retirement signal bypasses the streak.
     * On an already-SUSPENDED webhook it is a failed trial: the ladder climbs, nothing else changes.
     */
    private function recordGoneFailure(string $webhookId): void
    {
        $suspension = RetryableTransaction::retryable($this->connection, function () use ($webhookId): ?WebhookSuspendedEvent {
            $row = $this->lockHealthRow($webhookId);

            if ($row === null) {
                return $this->insertFreshRowForNonTransient($webhookId, nonTransientFailures: 0, suspend: true, cause: SuspensionCause::Gone);
            }

            $state = EndpointState::from((string) $row['endpoint_state']);

            if ($state === EndpointState::Suspended) {
                $this->advanceLadderLocked($webhookId, $row, EndpointState::Suspended, alsoCountAuthStreak: false);

                return null;
            }

            if ($state === EndpointState::Disabled) {
                return null;
            }

            return $this->suspendLocked($webhookId, $row, $state, nonTransientFailures: (int) $row['consecutive_non_transient_failures'], cause: SuspensionCause::Gone);
        });

        $this->finishSuspension($webhookId, $suspension);
    }

    /**
     * A transient result on a non-HEALTHY webhook: a released trial's failure climbs the ladder one
     * tier; in DEGRADED the same index is the cycle counter, and reaching the schedule's end means
     * SUSPENDED. Locks the row, then delegates to {@see advanceLadderLocked}.
     */
    private function advanceLadder(string $webhookId, EndpointState $expected): void
    {
        $suspension = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $expected): ?WebhookSuspendedEvent {
            $row = $this->lockHealthRow($webhookId);
            if ($row === null || EndpointState::from((string) $row['endpoint_state']) !== $expected) {
                // Raced away — absorb this stale result (the result side re-holds the row).
                return null;
            }

            return $this->advanceLadderLocked($webhookId, $row, $expected, alsoCountAuthStreak: false);
        });

        $this->finishSuspension($webhookId, $suspension);
    }

    /**
     * Ladder accounting under the row lock. Only a released trial may climb the ladder, exactly
     * once, at its result — and only if the cooldown was already elapsed when the result landed: a
     * result arriving while the cooldown still runs is a straggler (an in-flight delivery from
     * before the trip, or a crash-recovered row) and is absorbed without counting; the result side
     * re-holds its row. In DEGRADED, exhausting the schedule suspends. Returns the suspension
     * event when this advance suspended the webhook, null otherwise.
     *
     * @param array<string, mixed> $row the FOR-UPDATE-locked webhook_health row
     */
    private function advanceLadderLocked(string $webhookId, array $row, EndpointState $state, bool $alsoCountAuthStreak): ?WebhookSuspendedEvent
    {
        $cooldownElapsed = $row['cooldown_until'] === null || (string) $row['cooldown_until'] <= $this->now();
        $streak = (int) $row['consecutive_non_transient_failures'] + ($alsoCountAuthStreak ? 1 : 0);

        if (!$cooldownElapsed) {
            if ($alsoCountAuthStreak) {
                // The streak still counts (once per delivery, independent of the ladder), only the
                // ladder accounting is straggler-guarded.
                $this->connection->executeStatement(
                    'UPDATE webhook_health SET consecutive_non_transient_failures = :streak, updated_at = :now WHERE webhook_id = :id',
                    ['streak' => $streak, 'now' => $this->now(), 'id' => Uuid::fromHexToBytes($webhookId)]
                );
                $this->mirrorBcColumns($webhookId);
            }

            return null;
        }

        $top = \count($this->config->cooldownScheduleSeconds) - 1;
        $next = (int) $row['degraded_cycle_count'] + 1;

        if ($state === EndpointState::Degraded && $next >= \count($this->config->cooldownScheduleSeconds)) {
            // Schedule exhausted: the DEGRADED budget IS the schedule's length → SUSPENDED, ladder
            // staying at the top tier.
            return $this->suspendLocked($webhookId, $row, $state, nonTransientFailures: $streak, cause: SuspensionCause::ScheduleExhausted, entryIndex: $top);
        }

        $index = min($next, $top);
        $this->connection->executeStatement(
            'UPDATE webhook_health
             SET degraded_cycle_count = :index, cooldown_until = :cooldown,
                 consecutive_non_transient_failures = :streak, updated_at = :now
             WHERE webhook_id = :id',
            [
                'index' => $index,
                'cooldown' => $this->cooldownAt($index),
                'streak' => $streak,
                'now' => $this->now(),
                'id' => Uuid::fromHexToBytes($webhookId),
            ]
        );
        $this->mirrorBcColumns($webhookId);

        return null;
    }

    /**
     * The guarded SUSPENDED transition, under the caller's row lock. `suspended_since` is written
     * set-once (kept when already present — a SUSPENDED → DEGRADED → SUSPENDED flap never restarts
     * the 7-day clock; it clears only on reaching HEALTHY). The ladder entry tier is 0 on a direct
     * trip (auth streak / 410 — first trial after 5 minutes) and the top tier when arriving from
     * exhausted DEGRADED cycles. Mirrors inside the transaction; returns the suspension event.
     *
     * @param array<string, mixed> $row the FOR-UPDATE-locked webhook_health row
     */
    private function suspendLocked(string $webhookId, array $row, EndpointState $fromState, int $nonTransientFailures, SuspensionCause $cause, int $entryIndex = 0): WebhookSuspendedEvent
    {
        $now = $this->now();
        $since = $row['suspended_since'] !== null ? (string) $row['suspended_since'] : $now;

        $this->connection->executeStatement(
            'UPDATE webhook_health
             SET endpoint_state = :suspended, suspended_since = :since, degraded_cycle_count = :index,
                 cooldown_until = :cooldown, consecutive_non_transient_failures = :streak, updated_at = :now
             WHERE webhook_id = :id AND endpoint_state = :from',
            [
                'suspended' => EndpointState::Suspended->value,
                'since' => $since,
                'index' => $entryIndex,
                'cooldown' => $this->cooldownAt($entryIndex),
                'streak' => $nonTransientFailures,
                'now' => $now,
                'id' => Uuid::fromHexToBytes($webhookId),
                'from' => $fromState->value,
            ]
        );
        $this->mirrorBcColumns($webhookId);

        $ref = $this->webhookRefOf($webhookId);

        return new WebhookSuspendedEvent(
            $webhookId,
            $ref['appId'],
            $fromState,
            new \DateTimeImmutable($since),
            $cause,
            $ref['name'],
            $ref['eventName'],
            new \DateTimeImmutable($now),
        );
    }

    /**
     * Post-commit side effects of a `→ SUSPENDED` transition: hold the claimable backlog (the same
     * flip as `→ DEGRADED` — SUSPENDED holds, only DISABLED drops) and emit the lifecycle event.
     */
    private function finishSuspension(string $webhookId, ?WebhookSuspendedEvent $suspension): void
    {
        if ($suspension === null) {
            return;
        }

        $this->outboxStore->pauseDeliveriesForWebhook($webhookId);
        $this->dispatchBestEffort($suspension);
    }

    /**
     * Trial 2xx on a SUSPENDED webhook: one state toward HEALTHY — DEGRADED at ladder tier 0, both
     * streaks reset, `suspended_since` preserved (one success after a deep failure isn't proof of
     * health; HEALTHY is earned through the same ladder). The held backlog stays held.
     */
    private function deEscalateSuspendedToDegraded(string $webhookId): bool
    {
        $deEscalated = (int) $this->connection->executeStatement(
            'UPDATE webhook_health
             SET endpoint_state = :degraded, degraded_cycle_count = 0, cooldown_until = :cooldown,
                 consecutive_transient_failures = 0, consecutive_non_transient_failures = 0, updated_at = :now
             WHERE webhook_id = :id AND endpoint_state = :suspended',
            [
                'degraded' => EndpointState::Degraded->value,
                'cooldown' => $this->cooldownAt(0),
                'now' => $this->now(),
                'id' => Uuid::fromHexToBytes($webhookId),
                'suspended' => EndpointState::Suspended->value,
            ]
        );

        if ($deEscalated === 0) {
            return false;
        }

        $this->mirrorBcColumns($webhookId);
        $ref = $this->webhookRefOf($webhookId);
        $this->dispatchBestEffort(new WebhookDegradedEvent(
            $webhookId,
            $ref['appId'],
            EndpointState::Suspended,
            $ref['name'],
            $ref['eventName'],
            $this->clock->now(),
        ));

        return true;
    }

    /**
     * DEGRADED → HEALTHY recovery shared by a trial 2xx and idle promotion: the guarded transition,
     * the held-backlog resume (age-filtered; a no-op when idle), and the BC mirror, in one
     * transaction (a crash between them would strand `paused` rows nothing releases). Idle
     * promotion keeps the failure streaks — nothing was delivered, so nothing proved health; the
     * first transient failure after traffic resumes re-degrades immediately, a real 2xx clears them.
     */
    private function promoteDegradedToHealthy(string $webhookId, WebhookActivationTrigger $trigger): bool
    {
        $event = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $trigger): ?WebhookActivatedEvent {
            $row = $this->lockHealthRow($webhookId);
            if ($row === null || (string) $row['endpoint_state'] !== EndpointState::Degraded->value) {
                return null;
            }

            if (!$this->resetToHealthy($webhookId, keepStreaks: $trigger === WebhookActivationTrigger::Idle)) {
                return null;
            }

            $this->outboxStore->resumeDeliveriesForWebhook($webhookId);
            $this->mirrorBcColumns($webhookId);

            $ref = $this->webhookRefOf($webhookId);

            return new WebhookActivatedEvent(
                $webhookId,
                $ref['appId'],
                EndpointState::Degraded,
                $trigger,
                $ref['name'],
                $ref['eventName'],
                $this->clock->now(),
                $this->toDateTime($row['suspended_since']),
            );
        });

        if ($event === null) {
            return false;
        }

        $this->dispatchBestEffort($event);

        return true;
    }

    /**
     * @return array<string, mixed>|null the FOR-UPDATE-locked webhook_health row, or null when none exists
     */
    private function lockHealthRow(string $webhookId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT endpoint_state, consecutive_transient_failures, consecutive_non_transient_failures,
                    degraded_cycle_count, cooldown_until, suspended_since
             FROM webhook_health WHERE webhook_id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        return \is_array($row) ? $row : null;
    }

    /**
     * Half-open trial admission for a SUSPENDED webhook on natural traffic. Only with nothing held
     * (else the task releases the oldest held row instead) and nothing in flight; once the cooldown
     * elapses, the ladder is advanced one tier AT ADMISSION — re-arming the cooldown so a burst sees
     * the future lease and skips (exactly one trial), and so the trial's own failure result lands as
     * a straggler and cannot count a second time. A 2xx resets everything via recordSuccess anyway.
     */
    private function admitSuspendedTrial(string $webhookId): WebhookDispatchDecision
    {
        if ($this->hasHeldRow($webhookId)) {
            return WebhookDispatchDecision::Skip;
        }

        $admitted = RetryableTransaction::retryable($this->connection, function () use ($webhookId): bool {
            $row = $this->lockHealthRow($webhookId);
            if ($row === null || (string) $row['endpoint_state'] !== EndpointState::Suspended->value) {
                return false;
            }
            if ($row['cooldown_until'] !== null && (string) $row['cooldown_until'] > $this->now()) {
                return false;
            }
            if ($this->hasHeldRow($webhookId) || $this->hasInFlightRow($webhookId)) {
                return false;
            }

            $top = \count($this->config->cooldownScheduleSeconds) - 1;
            $index = min((int) $row['degraded_cycle_count'] + 1, $top);
            $this->connection->executeStatement(
                'UPDATE webhook_health
                 SET degraded_cycle_count = :index, cooldown_until = :cooldown, updated_at = :now
                 WHERE webhook_id = :id AND endpoint_state = :suspended',
                [
                    'index' => $index,
                    'cooldown' => $this->cooldownAt($index),
                    'now' => $this->now(),
                    'id' => Uuid::fromHexToBytes($webhookId),
                    'suspended' => EndpointState::Suspended->value,
                ]
            );

            return true;
        });

        return $admitted ? WebhookDispatchDecision::Deliver : WebhookDispatchDecision::Skip;
    }

    private function hasHeldRow(string $webhookId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery d
             JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
             WHERE d.webhook_id = :id AND d.delivery_status = :paused
               AND el.delivery_status NOT IN (:success, :failed)
             LIMIT 1',
            [
                'id' => Uuid::fromHexToBytes($webhookId),
                'paused' => 'paused',
                'success' => 'success',
                'failed' => 'failed',
            ]
        );
    }

    /**
     * Fail-open insert for the operator kill on a webhook without a health row.
     */
    private function insertFreshDisabledRow(string $webhookId): ?WebhookDisabledEvent
    {
        try {
            $this->connection->executeStatement(
                'INSERT INTO webhook_health (webhook_id, endpoint_state, disabled_since, disabled_origin, created_at)
                 VALUES (:id, :disabled, :now, :origin, :now)',
                [
                    'id' => Uuid::fromHexToBytes($webhookId),
                    'disabled' => EndpointState::Disabled->value,
                    'now' => $this->now(),
                    'origin' => DisabledOrigin::Operator->value,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            // The row appeared concurrently; the caller's gesture is re-evaluated against it on the
            // next write — absorbing one insert race keeps the guarded-transition invariant.
            return null;
        }

        $this->mirrorBcColumns($webhookId);

        return new WebhookDisabledEvent($webhookId, $this->appIdOf($webhookId), EndpointState::Healthy, DisabledOrigin::Operator);
    }

    /**
     * Fail-open insert for a non-transient failure on a webhook without a health row. Returns the
     * suspension event when the fresh row suspends immediately (410, or an auth threshold of 1).
     */
    private function insertFreshRowForNonTransient(string $webhookId, int $nonTransientFailures, bool $suspend, SuspensionCause $cause): ?WebhookSuspendedEvent
    {
        $now = $this->now();

        try {
            $this->connection->executeStatement(
                'INSERT INTO webhook_health
                    (webhook_id, endpoint_state, consecutive_non_transient_failures, degraded_cycle_count,
                     cooldown_until, suspended_since, created_at)
                 VALUES (:id, :state, :streak, 0, :cooldown, :since, :now)',
                [
                    'id' => Uuid::fromHexToBytes($webhookId),
                    'state' => $suspend ? EndpointState::Suspended->value : EndpointState::Healthy->value,
                    'streak' => $nonTransientFailures,
                    'cooldown' => $suspend ? $this->cooldownAt(0) : null,
                    'since' => $suspend ? $now : null,
                    'now' => $now,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            // The row appeared concurrently — the failure is re-recorded against it on the caller's
            // retry path; absorbing one insert race is safe (the streak is evidence, not a ledger).
            return null;
        }

        $this->mirrorBcColumns($webhookId);

        if (!$suspend) {
            return null;
        }

        $ref = $this->webhookRefOf($webhookId);

        return new WebhookSuspendedEvent(
            $webhookId,
            $ref['appId'],
            EndpointState::Healthy,
            new \DateTimeImmutable($now),
            $cause,
            $ref['name'],
            $ref['eventName'],
            new \DateTimeImmutable($now),
        );
    }

    private function hasInFlightRow(string $webhookId): bool
    {
        // Join webhook_event_log and exclude terminal statuses, exactly as fetchDue/markRunning do: a
        // rolling-deploy can leave a stale `running` webhook_delivery row whose event_log already reached
        // SUCCESS/FAILED. Such a row is never claimable, so treating it as "in flight" would wedge a
        // DEGRADED webhook forever — the tick would neither release a trial nor idle-promote.
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery d
             JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
             WHERE d.webhook_id = :id
               AND d.delivery_status IN (:queued, :pendingRetry, :running)
               AND el.delivery_status NOT IN (:success, :failed)
             LIMIT 1',
            [
                'id' => Uuid::fromHexToBytes($webhookId),
                'queued' => 'queued',
                'pendingRetry' => 'pending_retry',
                'running' => 'running',
                'success' => 'success',
                'failed' => 'failed',
            ]
        );
    }

    /**
     * The HEALTHY reset shared by the trial-2xx promotion, idle promotion, and manual reactivation —
     * one column list to keep in sync with the schema. Clears the ladder, the cooldown, and every
     * episode marker; `$keepStreaks` preserves the failure counters for idle promotion (nothing was
     * delivered to prove health). Returns true when a row actually transitioned.
     */
    private function resetToHealthy(string $webhookId, bool $keepStreaks): bool
    {
        $streakReset = $keepStreaks
            ? ''
            : 'consecutive_transient_failures = 0, consecutive_non_transient_failures = 0,';

        return $this->connection->executeStatement(
            \sprintf(
                'UPDATE webhook_health
                 SET endpoint_state = :healthy, %s degraded_cycle_count = 0,
                     cooldown_until = NULL, suspended_since = NULL, disabled_since = NULL,
                     disabled_origin = NULL, updated_at = :now
                 WHERE webhook_id = :id AND endpoint_state <> :healthy',
                $streakReset
            ),
            [
                'healthy' => EndpointState::Healthy->value,
                'now' => $this->now(),
                'id' => Uuid::fromHexToBytes($webhookId),
            ]
        ) > 0;
    }

    /**
     * Mirrors the legacy `webhook.active`/`error_count` columns from the CURRENT health row in one
     * JOINed write, so a stale transition's late mirror can never reinstate the wrong state (e.g. a
     * DEGRADED mirror clobbering a concurrent SUSPEND). Per the ADR BC table: active = HEALTHY/DEGRADED;
     * HEALTHY mirrors error_count = 0 (even when idle promotion kept the internal streaks); every other
     * state mirrors the dominant streak — GREATEST(transient, non-transient) — so an auth-suspended
     * endpoint whose transient counter never moved still reports a non-zero error_count.
     * Per-webhook only — no RelatedWebhooks sibling propagation.
     */
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

    /**
     * Best-effort post-commit emission: the transition is already committed, so a throwing listener
     * must not bubble into the delivery path — the events are advisory, the row is the truth.
     */
    private function dispatchBestEffort(object $event): void
    {
        try {
            $this->eventDispatcher->dispatch($event);
        } catch (\Throwable $e) {
            $this->logger->warning('Webhook lifecycle event listener failed', [
                'event' => $event::class,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * The webhook row's identity for a lifecycle event — one PK SELECT, same cost as the former
     * app-id-only lookup. All-null when the webhook row vanished between the transition and the
     * emission (delete race): the event still fires, keyed by id.
     *
     * @return array{appId: ?string, name: ?string, eventName: ?string}
     */
    private function webhookRefOf(string $webhookId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(app_id)) AS app_id, name, event_name FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        return [
            'appId' => \is_array($row) && \is_string($row['app_id']) ? $row['app_id'] : null,
            'name' => \is_array($row) && \is_string($row['name']) ? $row['name'] : null,
            'eventName' => \is_array($row) && \is_string($row['event_name']) ? $row['event_name'] : null,
        ];
    }

    private function toDateTime(mixed $storageValue): ?\DateTimeImmutable
    {
        return \is_string($storageValue) ? new \DateTimeImmutable($storageValue) : null;
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

        // Fail-open: a missing health row reads as HEALTHY — never silently blocks dispatch or recovery.
        return $state === false ? EndpointState::Healthy : EndpointState::from((string) $state);
    }

    private function now(): string
    {
        return $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
