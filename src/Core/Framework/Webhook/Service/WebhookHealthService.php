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
use Shopware\Core\Framework\Webhook\Health\WebhookDispatchDecision;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;

/**
 * Owns webhook health from end to end (#16565). With `WEBHOOKS_REWORK` off it runs the legacy
 * shared-counter disable path ({@see recordLegacyFailure}/{@see resetErrorCount}). With the flag on
 * it runs the new per-webhook circuit breaker: the {@see EndpointHealth} + {@see EndpointLifecycle}
 * state machine stored in the internal `webhook_health` table.
 *
 * Concurrency, flag on: every state change first reads the health row with `FOR UPDATE` inside a
 * retryable transaction, then writes with a WHERE clause that checks the state it read. If another
 * writer changed the state in between, the UPDATE matches zero rows, and the side effects
 * (pause/resume/mirror) only run when the UPDATE really changed a row. So two writers can never
 * both perform the same transition.
 *
 * The BC mirror ({@see mirrorBcColumns}) keeps the legacy `webhook.active`/`error_count` columns in
 * sync. It derives them from the current health row in a single JOINed UPDATE — never from what the
 * caller thinks the state is. That way a mirror that runs late cannot set `active = 1` on an
 * endpoint that a concurrent transition just suspended. The mirror touches one webhook only: there
 * is no `RelatedWebhooks` sibling propagation (that cross-webhook blast radius is exactly the bug
 * this rework removes).
 *
 * Lifecycle events ({@see WebhookActivatedEvent} and friends) are emitted after commit and are
 * best-effort: the `webhook_health` row is the source of truth, and a failing listener never
 * affects the transition.
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
            // SUSPENDED keeps its already-held backlog and drops new events without writing
            // anything. One exception: when nothing is held, nothing is in flight, and the
            // cooldown has elapsed, the next natural event is let through as the half-open trial.
            // DISABLED never recovers from traffic.
            EndpointState::Suspended => $this->admitSuspendedTrial($webhookId),
            EndpointState::Disabled => WebhookDispatchDecision::Skip,
        };
    }

    public function recordSuccess(string $webhookId): void
    {
        // One unguarded read decides which branch handles the 2xx. That is safe because each
        // branch's UPDATE still checks the state in its WHERE clause — if another writer changed
        // the state after our read, the UPDATE matches zero rows and nothing happens. The common
        // case (a HEALTHY webhook with clean streaks, often with no health row at all) stays at
        // this single SELECT plus at most the legacy error_count reconcile below.
        $row = $this->connection->fetchAssociative(
            'SELECT wh.endpoint_state, wh.consecutive_transient_failures, wh.consecutive_non_transient_failures
             FROM webhook_health wh WHERE wh.webhook_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );
        $state = \is_array($row) ? EndpointState::from((string) $row['endpoint_state']) : EndpointState::Healthy;

        // A 2xx moves the webhook up exactly one state. SUSPENDED → DEGRADED: the ladder resets
        // to tier 0 but suspended_since is kept — HEALTHY must be earned through the same ladder.
        // DEGRADED → HEALTHY: full reset, and the held backlog resumes (old events filtered out
        // by age).
        if ($state === EndpointState::Suspended && $this->deEscalateSuspendedToDegraded($webhookId)) {
            return;
        }

        if ($state === EndpointState::Degraded && $this->promoteDegradedToHealthy($webhookId, WebhookActivationTrigger::Trial)) {
            return;
        }

        // HEALTHY with partial streaks: any 2xx resets both failure counters, so failures from
        // separate outages don't add up over time.
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

        // Legacy reconcile: trunk reset webhook.error_count on every success, and the generic
        // /api/webhook API reads, filters, and sorts on that column. A HEALTHY endpoint should
        // mirror error_count = 0. But mirrorBcColumns only runs on a state transition, and its
        // inner JOIN cannot reach a webhook that has no health row. So we reset the counter here
        // for a healthy webhook whose legacy counter drifted above 0 — including one with no
        // health row yet (fail-open HEALTHY) that collected a legacy count before the flag was on.
        // This touches one webhook only (no RelatedWebhooks sibling propagation — that
        // blast-radius bug is what this rework fixes). `active` is deliberately left alone:
        // trunk's success path never reactivated a webhook, and the state guard in the WHERE
        // clause keeps a row that was concurrently SUSPENDED/DISABLED safe.
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
        // Each handler returns the webhook's resulting state. The handler already holds (or just
        // wrote) the authoritative row, so the result side does not need a second read to place
        // its delivery row.
        return match ($classification) {
            // A successful delivery goes through recordSuccess; reaching here is a caller bug.
            // Crash loudly instead of recording the success as a failure.
            ErrorClassification::Success => throw WebhookException::unexpectedClassification($classification->value),
            // Payload/message-specific errors (400, other unlisted 4xx) never touch endpoint
            // health and never use up a trial: no cooldown was advanced, so the next tick releases
            // the next-oldest held row. The result side fails the delivery row based on the
            // classification alone.
            ErrorClassification::NonTransientPayload => $this->currentState($webhookId),
            // An auth rejection counts toward the non-transient streak and suspends at the
            // threshold. 410 Gone is the endpoint saying it is retired, so it suspends immediately.
            ErrorClassification::NonTransientAuth => $this->recordNonTransientFailure($webhookId, countsStreak: true),
            ErrorClassification::NonTransientEndpoint => $this->recordNonTransientFailure($webhookId, countsStreak: false),
            ErrorClassification::TransientNetwork,
            ErrorClassification::TransientServer,
            ErrorClassification::TransientRateLimit,
            ErrorClassification::TransientRedirect => $this->recordTransientFailure($webhookId, $attempt),
        };
    }

    /**
     * One scheduled tick that runs the five clocked duties (ADR §Half-open recovery): trial
     * releases for both non-HEALTHY states, idle promotion, the 7-day retirement, cleanup of
     * crash leftovers, and healing of stale holds. It also pauses the suspension clock for
     * deactivated apps. Each duty is a cheap indexed check, and every per-webhook action runs
     * in its own short transaction.
     */
    public function tick(): int
    {
        $this->shiftPausedSuspensionClocks();

        return $this->runDueReleases()
            + $this->retireSuspendedPastBound()
            + $this->cancelSurplusSuspendedInFlight()
            + $this->healStrandedHolds();
    }

    public function pauseSuspensionClockForApp(string $appId): void
    {
        // Set the shift cursor (updated_at) to the deactivation moment, so the first tick shifts
        // by exactly the deactivated time — not by the gap back to the last health write.
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
        $event = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $trigger): ?WebhookActivatedEvent {
            $id = Uuid::fromHexToBytes($webhookId);

            // Lock the webhook row to serialise with concurrent transitions; bail out if the
            // webhook is gone. The same read also fetches the app id for the event, so no
            // separate lookup is needed.
            $appId = $this->connection->fetchOne(
                'SELECT COALESCE(LOWER(HEX(app_id)), :appless) FROM webhook WHERE id = :id FOR UPDATE',
                ['id' => $id, 'appless' => '']
            );
            if (!\is_string($appId)) {
                return null;
            }
            $appId = $appId === '' ? null : $appId;

            $row = $this->connection->fetchAssociative(
                'SELECT endpoint_state, suspended_since, disabled_origin
                 FROM webhook_health WHERE webhook_id = :id FOR UPDATE',
                ['id' => $id]
            );

            if (!\is_array($row)) {
                // No health row means fail-open HEALTHY: there is no state to transition. But the
                // operator's gesture still repairs a drifted legacy mirror — a flag-off
                // auto-disable may have left active = 0 while the health model already reads the
                // webhook as healthy.
                $this->connection->executeStatement(
                    'UPDATE webhook SET active = 1, error_count = 0 WHERE id = :id',
                    ['id' => $id]
                );
                $this->outboxStore->resumeDeliveriesForWebhook($webhookId);

                return null;
            }

            $fromState = EndpointState::from((string) $row['endpoint_state']);

            $transitioned = $this->reactivationPolicyAllows($trigger, $fromState, $row['disabled_origin'])
                && $this->resetToHealthy($webhookId, keepStreaks: false);

            // On a real transition, or on an already-HEALTHY webhook, heal idempotently: the
            // mirror repairs drifted legacy columns and the resume releases a backlog stranded by
            // a crash — both are no-ops when there is nothing to repair. This runs inside the
            // transaction so a crash cannot commit the transition without it (a HEALTHY row that
            // WebhookLoader's active=1 filter still excludes would be a zombie nothing can
            // recover). A REFUSED gesture (an echo on DEGRADED, or automation hitting an operator
            // kill) must NOT resume: that would release a deliberately held backlog onto an
            // endpoint that is still breaking. It does still re-derive the BC mirror (the state
            // is untouched): the app persister force-writes active = 1 on every app update, and a
            // refused recovery must not leave an operator-killed webhook reading as active.
            if (!$transitioned && $fromState !== EndpointState::Healthy) {
                $this->mirrorBcColumns($webhookId);

                return null;
            }

            $this->mirrorBcColumns($webhookId);
            $this->outboxStore->resumeDeliveriesForWebhook($webhookId);

            if (!$transitioned) {
                return null;
            }

            return new WebhookActivatedEvent(
                $webhookId,
                $appId,
                $fromState,
                $trigger,
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
        // An app install or update is a deliberate config refresh — a clean slate that replaces
        // exactly the things that usually broke the endpoint. Reset every non-HEALTHY webhook of
        // the app through the per-webhook reactivate (resume, mirror, one WebhookActivatedEvent
        // each). The reactivation policy itself refuses operator-disabled webhooks, so a
        // merchant's explicit kill survives a routine app update.
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

        $reset = 0;
        foreach ($webhookIds as $webhookId) {
            $reset += $this->reactivate($webhookId, WebhookActivationTrigger::AppReset);
        }

        return $reset;
    }

    public function disableByOperatorOnActiveFlip(string $webhookId): int
    {
        // The admin `PATCH active = false` gesture. We only read intent from a write that
        // actually flips the mirrored value. HEALTHY/DEGRADED mirror active = true, so the write
        // is a real kill there. On SUSPENDED/DISABLED the mirrored value is already false, so the
        // same write is just an echo (e.g. a full-entity round-trip while suspended) and must not
        // count as operator intent — the unambiguous gesture there is the dedicated deactivate
        // action, {@see disableByOperator}.
        return $this->disableFrom($webhookId, [EndpointState::Healthy, EndpointState::Degraded]);
    }

    public function disableByOperator(string $webhookId): int
    {
        // The dedicated admin deactivate action. It carries intent in any state, covering what
        // the PATCH cannot express on a SUSPENDED/DISABLED webhook whose mirrored value is
        // already false.
        return $this->disableFrom($webhookId, null);
    }

    /**
     * @deprecated tag:v6.8.0 - Pre-rework shared-counter failure handling. Runs only with WEBHOOKS_REWORK
     * off and is removed together with the `webhook.active`/`error_count` columns. Renamed from
     * `recordFailure` so the per-delivery {@see EndpointHealth::recordFailure} can use that name when the
     * flag is on.
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
     * @deprecated tag:v6.8.0 - Pre-rework shared-counter reset. Runs only with WEBHOOKS_REWORK off and is
     * removed together with the legacy columns. With the flag on, {@see recordSuccess} owns the per-webhook reset.
     */
    public function resetErrorCount(string $webhookId): void
    {
        $this->relatedWebhooks->updateRelated($webhookId, ['error_count' => 0], Context::createDefaultContext());
    }

    /**
     * Duties 1 + 2. For each DEGRADED or SUSPENDED webhook whose cooldown has elapsed, release the
     * oldest held row as the trial. A DEGRADED webhook with nothing held and nothing in flight is
     * promoted to HEALTHY instead (idle promotion). SUSPENDED never idle-promotes — the gate
     * admits the next natural event as its trial. And SUSPENDED webhooks of deactivated apps get
     * no trials at all: their events are filtered out before they reach the gate, so their
     * suspension clock is paused, not running.
     */
    private function runDueReleases(): int
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

        $acted = 0;
        foreach ($candidates as $webhookId) {
            $acted += RetryableTransaction::retryable($this->connection, function () use ($webhookId, $now): int {
                // Serialise concurrent ticks on this webhook: hold the health row FOR UPDATE
                // across the in-flight check, the release, and the idle promotion. Without the
                // lock, two ticks could both pass the in-flight check and release two trials, or
                // one could idle-promote while the other's just-released trial is still in flight.
                // Re-check the state under the lock.
                $row = $this->lockHealthRow($webhookId);
                if ($row === null) {
                    return 0;
                }
                $state = EndpointState::from((string) $row['endpoint_state']);
                if ($state !== EndpointState::Degraded && $state !== EndpointState::Suspended) {
                    return 0;
                }
                if ($row['cooldown_until'] !== null && (string) $row['cooldown_until'] > $now) {
                    return 0;
                }

                // An earlier release is still in flight (claimable or running), so do nothing.
                // The ladder advances on that trial's result, not on the wall clock — a slow
                // worker must not march an endpoint to SUSPENDED without any delivery evidence.
                if ($this->outboxStore->hasInFlightRows($webhookId)) {
                    return 0;
                }

                // Something is held: release exactly one row as the trial; its result drives the
                // ladder. The release itself never counts as a ladder step — the cooldown stays
                // elapsed, so the trial's result is the thing that counts (and re-arms the next
                // tier).
                if ($this->outboxStore->releaseOneTrial($webhookId) !== null) {
                    return 1;
                }

                if ($state === EndpointState::Suspended) {
                    // Nothing held: the gate admits the next natural event as the trial.
                    return 0;
                }

                // Truly idle (nothing held, nothing in flight): promote to HEALTHY. Staying
                // DEGRADED instead would stick an idle webhook there forever — it can only
                // advance through a trial result, and there is no traffic to produce one.
                return $this->promoteDegradedToHealthy($webhookId, WebhookActivationTrigger::Idle) ? 1 : 0;
            });
        }

        return $acted;
    }

    /**
     * Duty 3: a webhook SUSPENDED for longer than `suspended_since + max_suspended_days` retires
     * to DISABLED (origin: escalation). This is time-based, not traffic-based, so a dead endpoint
     * with no traffic still retires. Webhooks of deactivated apps are skipped: their clock is
     * paused ({@see shiftPausedSuspensionClocks}), and retiring them on the first tick after
     * reactivation would punish time in which the endpoint had no way to recover.
     */
    private function retireSuspendedPastBound(): int
    {
        $cutoff = $this->clock->now()
            ->modify(\sprintf('-%d days', $this->config->maxSuspendedDays))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        /** @var array<string, string|null> $candidates webhook id (hex) => app id (hex or null) */
        $candidates = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(wh.webhook_id)), LOWER(HEX(w.app_id))
             FROM webhook_health wh
             LEFT JOIN webhook w ON w.id = wh.webhook_id
             LEFT JOIN app a ON a.id = w.app_id
             WHERE wh.endpoint_state = :suspended
               AND wh.suspended_since IS NOT NULL AND wh.suspended_since <= :cutoff
               AND (a.id IS NULL OR a.active = 1)',
            ['suspended' => EndpointState::Suspended->value, 'cutoff' => $cutoff]
        );

        $retired = 0;
        foreach ($candidates as $webhookId => $appId) {
            $event = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $appId, $cutoff): ?WebhookDisabledEvent {
                // SKIP LOCKED skips a candidate that a concurrent tick or trial is touching right
                // now. And if a trial already recovered the webhook to DEGRADED, the guarded
                // UPDATE below matches zero rows and we do nothing.
                $locked = $this->connection->fetchOne(
                    'SELECT 1 FROM webhook_health
                     WHERE webhook_id = :id AND endpoint_state = :suspended AND suspended_since <= :cutoff
                     FOR UPDATE SKIP LOCKED',
                    [
                        'id' => Uuid::fromHexToBytes($webhookId),
                        'suspended' => EndpointState::Suspended->value,
                        'cutoff' => $cutoff,
                    ]
                );
                if ($locked === false) {
                    return null;
                }

                if ($this->disableRowLocked($webhookId, EndpointState::Suspended, DisabledOrigin::Escalation) === 0) {
                    return null;
                }

                return new WebhookDisabledEvent($webhookId, $appId, EndpointState::Suspended, DisabledOrigin::Escalation);
            });

            if ($event === null) {
                continue;
            }

            // DISABLED cancels every undelivered row — queued, held, and mid-flight alike.
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
     * Duty 4: on a SUSPENDED webhook, only the one deliberately released trial row may be in
     * flight. Crash recovery can bring pre-suspension deliveries back as claimable rows; this
     * duty cancels those extras.
     */
    private function cancelSurplusSuspendedInFlight(): int
    {
        $acted = 0;
        foreach ($this->outboxStore->findSuspendedWebhookIdsWithClaimableRows() as $webhookId) {
            $acted += $this->outboxStore->cancelSurplusInFlightRows($webhookId) > 0 ? 1 : 0;
        }

        return $acted;
    }

    /**
     * Duty 5: resume `paused` rows stuck on a HEALTHY webhook. This happens when the gate decided
     * Hold at the same moment a recovery ran — the gate persists its decision as-is, and this
     * duty cleans up that race afterwards.
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
     * Pauses the suspension clock while an app is deactivated (ADR §SUSPENDED). Each tick pushes
     * `suspended_since` forward by the time since the row's last write, so only time with a live
     * recovery path counts toward the 7-day bound. The net effect is the same as rebasing the
     * clock at reactivation, but without storing the deactivation timestamp anywhere.
     * `updated_at` works as the shift cursor; {@see pauseSuspensionClockForApp} sets it at
     * deactivation. MySQL applies SET clauses left to right, so `suspended_since` still reads
     * the cursor value from before the shift.
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

    /**
     * The reactivation policy lives here on the mechanism, keyed by who is asking (ADR §Admin API
     * backwards-compat + §Clean-slate reset). That way a new caller cannot accidentally revive a
     * merchant's explicit kill just by forgetting a pre-filter:
     *
     *  - Manual (admin `PATCH active = true`): only an actual value flip carries intent, so only
     *    SUSPENDED/DISABLED transition (an echo on HEALTHY/DEGRADED is a no-op for the state
     *    machine). The operator gesture reverses ANY `disabled_origin`.
     *  - AppReset / AppReactivateApi (automation): any non-HEALTHY state may transition, EXCEPT
     *    an operator-disabled webhook — automation never undoes a human's deliberate kill.
     *  - Trial / Idle never reach reactivate(); they go through recordSuccess and the tick.
     */
    private function reactivationPolicyAllows(WebhookActivationTrigger $trigger, EndpointState $fromState, mixed $disabledOrigin): bool
    {
        if ($fromState === EndpointState::Healthy) {
            return false;
        }

        return match ($trigger) {
            WebhookActivationTrigger::Manual => $fromState === EndpointState::Suspended || $fromState === EndpointState::Disabled,
            WebhookActivationTrigger::AppReset,
            WebhookActivationTrigger::AppReactivateApi => !($fromState === EndpointState::Disabled && $disabledOrigin === DisabledOrigin::Operator->value),
            // Trial/Idle recoveries go through recordSuccess and the tick, never this method — refuse.
            WebhookActivationTrigger::Trial,
            WebhookActivationTrigger::Idle => false,
        };
    }

    /**
     * @param list<EndpointState>|null $onlyFrom restricts which states may transition; null = any
     */
    private function disableFrom(string $webhookId, ?array $onlyFrom): int
    {
        // The fail-open INSERT below is load-bearing. A webhook that never failed has no health
        // row, but its already-enqueued deliveries keep delivering after active = false. A late
        // failure on one of them would insert a fresh HEALTHY row and mirror active = 1 —
        // bringing back the endpoint the operator just switched off.
        $event = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $onlyFrom): ?WebhookDisabledEvent {
            $row = $this->lockHealthRow($webhookId);

            if ($row === null) {
                return $this->insertFreshDisabledRow($webhookId);
            }

            $fromState = EndpointState::from((string) $row['endpoint_state']);

            if ($fromState === EndpointState::Disabled) {
                // Already DISABLED, so there is no state change. But the dedicated action still
                // makes the kill the operator's: a webhook disabled by escalation is re-stamped
                // as operator-disabled, so an app update can no longer revive it.
                if ($onlyFrom === null) {
                    $this->connection->executeStatement(
                        'UPDATE webhook_health SET disabled_origin = :origin, updated_at = :now
                         WHERE webhook_id = :id AND endpoint_state = :disabled',
                        [
                            'origin' => DisabledOrigin::Operator->value,
                            'now' => $this->now(),
                            'id' => Uuid::fromHexToBytes($webhookId),
                            'disabled' => EndpointState::Disabled->value,
                        ]
                    );
                }

                return null;
            }

            if ($onlyFrom !== null && !\in_array($fromState, $onlyFrom, true)) {
                return null;
            }

            if ($this->disableRowLocked($webhookId, $fromState, DisabledOrigin::Operator) === 0) {
                return null;
            }

            return new WebhookDisabledEvent($webhookId, $this->appIdOf($webhookId), $fromState, DisabledOrigin::Operator);
        });

        if ($event === null) {
            return 0;
        }

        // DISABLED cancels every undelivered row — the operator's kill also stops a backlog that
        // was still deliverable.
        $this->outboxStore->dropBacklogForWebhook($webhookId, WebhookOutboxStore::DROP_REASON_DISABLED);
        $this->dispatchBestEffort($event);
        $this->logger->warning('Webhook endpoint disabled by operator', ['webhookId' => $webhookId]);

        return 1;
    }

    /**
     * The guarded transition to DISABLED, shared by the operator kill and the escalation
     * retirement. Runs under the caller's row lock and mirrors inside the transaction. Returns
     * the affected-row count so callers only drop the backlog and emit the event when the
     * transition really happened.
     */
    private function disableRowLocked(string $webhookId, EndpointState $fromState, DisabledOrigin $origin): int
    {
        $disabled = (int) $this->connection->executeStatement(
            'UPDATE webhook_health
             SET endpoint_state = :disabled, disabled_since = :now, disabled_origin = :origin,
                 cooldown_until = NULL, updated_at = :now
             WHERE webhook_id = :id AND endpoint_state = :from',
            [
                'disabled' => EndpointState::Disabled->value,
                'now' => $this->now(),
                'origin' => $origin->value,
                'id' => Uuid::fromHexToBytes($webhookId),
                'from' => $fromState->value,
            ]
        );

        if ($disabled > 0) {
            $this->mirrorBcColumns($webhookId);
        }

        return $disabled;
    }

    private function recordTransientFailure(string $webhookId, int $attempt): EndpointState
    {
        $current = $this->currentState($webhookId);

        // In DEGRADED/SUSPENDED every transient result belongs to a released trial — or to a
        // straggler; the ladder advance tells them apart by whether the cooldown had elapsed.
        // DISABLED swallows the result.
        if ($current === EndpointState::Degraded || $current === EndpointState::Suspended) {
            return $this->advanceLadder($webhookId, $current);
        }

        if ($current === EndpointState::Disabled) {
            return $current;
        }

        // Only a delivery's first attempt counts toward the threshold. That way one delivery's
        // own retries can never cross the threshold by themselves (degraded_threshold equals the
        // per-delivery retry budget).
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
        $id = Uuid::fromHexToBytes($webhookId);

        $outcome = RetryableTransaction::retryable($this->connection, function () use ($id, $threshold, $now, $firstCooldown): ?EndpointState {
            // Increment and transition in one guarded UPDATE each, on an existing HEALTHY row,
            // common case (still below the threshold) first. The crossing UPDATE checks
            // `+ 1 >= threshold` itself, so an affected row count of 1 already proves the
            // transition happened — no extra read that another writer could race. `+ 1` sees the
            // pre-update count because the increment is part of the same SET clause. Returns null
            // when no HEALTHY row matched (it changed concurrently, or it does not exist yet).
            $apply = function () use ($id, $threshold, $now, $firstCooldown): ?EndpointState {
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
                        'id' => $id,
                        'threshold' => $threshold,
                    ]
                );

                return $crossed > 0 ? EndpointState::Degraded : null;
            };

            $result = $apply();
            if ($result !== null) {
                return $result;
            }

            // No HEALTHY row matched. Either the row exists but already moved to a non-HEALTHY
            // state (swallow the result — that transition is not ours), or no row exists yet
            // (fail-open first failure — insert it).
            if ((bool) $this->connection->fetchOne('SELECT 1 FROM webhook_health WHERE webhook_id = :id', ['id' => $id])) {
                return null;
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

                return $crosses ? EndpointState::Degraded : EndpointState::Healthy;
            } catch (UniqueConstraintViolationException) {
                // The row was inserted concurrently. Re-run the guarded update so this failure
                // still counts instead of being dropped.
                return $apply();
            }
        });

        if ($outcome === EndpointState::Degraded) {
            // Hold the rest of the backlog for the ladder; the result side holds the in-flight row itself.
            $this->outboxStore->pauseDeliveriesForWebhook($webhookId);
            $this->dispatchBestEffort(new WebhookDegradedEvent($webhookId, $this->appIdOf($webhookId), EndpointState::Healthy));
        }

        if ($outcome !== null) {
            $this->mirrorBcColumns($webhookId);

            return $outcome;
        }

        // Another writer moved the state — report what it is now.
        return $this->currentState($webhookId);
    }

    /**
     * Records a non-transient failure. With `$countsStreak` (401/403) the streak advances once
     * per delivery — auth failures are terminal, so each delivery produces at most one result —
     * and the webhook suspends at the threshold. Without it (410 Gone) the webhook suspends
     * immediately: the endpoint's own retirement signal skips the streak. Transient failures
     * neither advance nor reset the streak; only a 2xx resets it. On a DEGRADED or
     * already-SUSPENDED webhook the result also counts as a failed trial: the ladder climbs
     * (with the straggler guard) and the streak keeps counting.
     */
    private function recordNonTransientFailure(string $webhookId, bool $countsStreak): EndpointState
    {
        $result = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $countsStreak): array {
            $row = $this->lockHealthRow($webhookId);

            if ($row === null) {
                // Fail-open first failure on a webhook without a health row.
                $streak = $countsStreak ? 1 : 0;
                $suspend = !$countsStreak || $this->config->nonTransientThreshold <= 1;
                $event = $this->insertFreshRowForNonTransient($webhookId, $streak, $suspend);

                return [$suspend ? EndpointState::Suspended : EndpointState::Healthy, $event];
            }

            $state = EndpointState::from((string) $row['endpoint_state']);

            if ($state === EndpointState::Suspended) {
                $event = $this->advanceLadderLocked($webhookId, $row, $state, alsoCountAuthStreak: $countsStreak);

                return [EndpointState::Suspended, $event];
            }

            if ($state === EndpointState::Disabled) {
                return [$state, null];
            }

            $streak = (int) $row['consecutive_non_transient_failures'] + ($countsStreak ? 1 : 0);
            if (!$countsStreak || $streak >= $this->config->nonTransientThreshold) {
                return [EndpointState::Suspended, $this->suspendLocked($webhookId, $row, $state, nonTransientFailures: $streak)];
            }

            if ($state === EndpointState::Degraded) {
                // Below the threshold, the auth failure still counts as a failed trial (ADR trial
                // results, rule 3): the ladder climbs and the cooldown re-arms — with the
                // straggler guard, and suspending when the schedule is exhausted. Leaving the
                // cooldown elapsed instead would release a fresh trial on every tick.
                $event = $this->advanceLadderLocked($webhookId, $row, $state, alsoCountAuthStreak: true);

                return [$event !== null ? EndpointState::Suspended : $state, $event];
            }

            $this->connection->executeStatement(
                'UPDATE webhook_health
                 SET consecutive_non_transient_failures = :streak, updated_at = :now
                 WHERE webhook_id = :id',
                ['streak' => $streak, 'now' => $this->now(), 'id' => Uuid::fromHexToBytes($webhookId)]
            );
            $this->mirrorBcColumns($webhookId);

            return [$state, null];
        });

        $this->finishSuspension($webhookId, $result[1]);

        return $result[0];
    }

    /**
     * A transient result on a non-HEALTHY webhook: a released trial's failure climbs the ladder
     * one tier. In DEGRADED the same index also counts the cycles, and reaching the end of the
     * schedule means SUSPENDED. Locks the row, then delegates to {@see advanceLadderLocked};
     * returns the state after the advance.
     */
    private function advanceLadder(string $webhookId, EndpointState $expected): EndpointState
    {
        $suspension = RetryableTransaction::retryable($this->connection, function () use ($webhookId, $expected): ?WebhookSuspendedEvent {
            $row = $this->lockHealthRow($webhookId);
            if ($row === null || EndpointState::from((string) $row['endpoint_state']) !== $expected) {
                // The state changed under us — swallow this stale result (the result side
                // re-holds the row).
                return null;
            }

            return $this->advanceLadderLocked($webhookId, $row, $expected, alsoCountAuthStreak: false);
        });

        $this->finishSuspension($webhookId, $suspension);

        return $suspension !== null ? EndpointState::Suspended : $expected;
    }

    /**
     * Ladder accounting under the row lock. Only a released trial may climb the ladder, exactly
     * once, when its result lands — and only if the cooldown had already elapsed at that moment.
     * A result that arrives while the cooldown is still running is a straggler (an in-flight
     * delivery from before the trip, or a crash-recovered row) and is swallowed without counting;
     * the result side re-holds its row. In DEGRADED, exhausting the schedule suspends the
     * webhook. Returns the suspension event when this advance suspended the webhook, null
     * otherwise.
     *
     * @param array<string, mixed> $row the FOR-UPDATE-locked webhook_health row
     */
    private function advanceLadderLocked(string $webhookId, array $row, EndpointState $state, bool $alsoCountAuthStreak): ?WebhookSuspendedEvent
    {
        $now = $this->now();
        $id = Uuid::fromHexToBytes($webhookId);
        $cooldownElapsed = $row['cooldown_until'] === null || (string) $row['cooldown_until'] <= $now;
        $streak = (int) $row['consecutive_non_transient_failures'] + ($alsoCountAuthStreak ? 1 : 0);

        if (!$cooldownElapsed) {
            if ($alsoCountAuthStreak) {
                // The streak still counts (once per delivery, independent of the ladder); only
                // the ladder accounting has the straggler guard.
                $this->connection->executeStatement(
                    'UPDATE webhook_health SET consecutive_non_transient_failures = :streak, updated_at = :now WHERE webhook_id = :id',
                    ['streak' => $streak, 'now' => $now, 'id' => $id]
                );
                $this->mirrorBcColumns($webhookId);
            }

            return null;
        }

        $top = \count($this->config->cooldownScheduleSeconds) - 1;
        $next = (int) $row['degraded_cycle_count'] + 1;

        if ($state === EndpointState::Degraded && $next >= \count($this->config->cooldownScheduleSeconds)) {
            // Schedule exhausted: the DEGRADED budget is exactly the schedule's length, so the
            // webhook suspends, with the ladder staying at the top tier.
            return $this->suspendLocked($webhookId, $row, $state, nonTransientFailures: $streak, entryIndex: $top);
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
                'now' => $now,
                'id' => $id,
            ]
        );

        if ($alsoCountAuthStreak) {
            // A pure tier advance changes neither the state nor a streak, so the mirror's
            // derived columns are already correct — mirror only when the streak moved.
            $this->mirrorBcColumns($webhookId);
        }

        return null;
    }

    /**
     * The guarded transition to SUSPENDED, under the caller's row lock. `suspended_since` is
     * written once and then kept: a SUSPENDED → DEGRADED → SUSPENDED flap never restarts the
     * 7-day clock, and the value only clears on reaching HEALTHY. The ladder entry tier is 0 on
     * a direct trip (auth streak or 410 — first trial after 5 minutes) and the top tier when
     * arriving from exhausted DEGRADED cycles. Mirrors inside the transaction; returns the
     * suspension event.
     *
     * @param array<string, mixed> $row the FOR-UPDATE-locked webhook_health row
     */
    private function suspendLocked(string $webhookId, array $row, EndpointState $fromState, int $nonTransientFailures, int $entryIndex = 0): WebhookSuspendedEvent
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

        return new WebhookSuspendedEvent($webhookId, $this->appIdOf($webhookId), $fromState, new \DateTimeImmutable($since));
    }

    /**
     * Post-commit side effects of a transition to SUSPENDED: hold the claimable backlog (the
     * same flip as for DEGRADED — SUSPENDED holds, only DISABLED drops) and emit the lifecycle
     * event.
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
     * A trial 2xx on a SUSPENDED webhook moves it one state toward HEALTHY: DEGRADED at ladder
     * tier 0, both streaks reset, `suspended_since` kept — one success after a deep failure is
     * not proof of health, so HEALTHY must be earned through the same ladder. The held backlog
     * stays held.
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
        $this->dispatchBestEffort(new WebhookDegradedEvent($webhookId, $this->appIdOf($webhookId), EndpointState::Suspended));

        return true;
    }

    /**
     * The DEGRADED → HEALTHY recovery shared by a trial 2xx and idle promotion: the guarded
     * transition, the resume of the held backlog (age-filtered; a no-op when idle), and the BC
     * mirror, all in one transaction — a crash between them would strand `paused` rows that
     * nothing ever releases. Idle promotion keeps the failure streaks: nothing was delivered, so
     * nothing proved health. The first transient failure after traffic resumes re-degrades the
     * webhook immediately; a real 2xx clears the streaks.
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

            return new WebhookActivatedEvent(
                $webhookId,
                $this->appIdOf($webhookId),
                EndpointState::Degraded,
                $trigger,
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
     * Half-open trial admission for a SUSPENDED webhook on natural traffic. It only applies when
     * nothing is held (otherwise the scheduled task releases the oldest held row instead) and
     * nothing is in flight. Once the cooldown has elapsed, the ladder advances one tier AT
     * ADMISSION, which re-arms the cooldown. That has two effects: a burst of events sees the
     * future cooldown and skips, so exactly one trial gets through; and the trial's own failure
     * result lands as a straggler, so it cannot count a second time. A 2xx resets everything via
     * recordSuccess anyway.
     */
    private function admitSuspendedTrial(string $webhookId): WebhookDispatchDecision
    {
        // Cheap pre-check outside any transaction; re-checked under the lock below.
        if ($this->outboxStore->hasHeldRows($webhookId)) {
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
            if ($this->outboxStore->hasHeldRows($webhookId) || $this->outboxStore->hasInFlightRows($webhookId)) {
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

    /**
     * Fail-open insert for the operator kill on a webhook that has no health row yet.
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
            // The row was inserted concurrently. Drop this one insert to keep the
            // guarded-transition invariant; the caller's gesture is re-evaluated against the new
            // row on the next write.
            return null;
        }

        $this->mirrorBcColumns($webhookId);

        return new WebhookDisabledEvent($webhookId, $this->appIdOf($webhookId), EndpointState::Healthy, DisabledOrigin::Operator);
    }

    /**
     * Fail-open insert for a non-transient failure on a webhook that has no health row yet.
     * Returns the suspension event when the fresh row suspends immediately (410, or an auth
     * threshold of 1).
     */
    private function insertFreshRowForNonTransient(string $webhookId, int $nonTransientFailures, bool $suspend): ?WebhookSuspendedEvent
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
            // The row was inserted concurrently — the caller's retry path records the failure
            // against it. Dropping one insert is safe: the streak is evidence of failure, not an
            // exact ledger.
            return null;
        }

        $this->mirrorBcColumns($webhookId);

        return $suspend
            ? new WebhookSuspendedEvent($webhookId, $this->appIdOf($webhookId), EndpointState::Healthy, new \DateTimeImmutable($now))
            : null;
    }

    /**
     * The reset to HEALTHY shared by the trial-2xx promotion, idle promotion, and manual
     * reactivation — one column list to keep in sync with the schema. Clears the ladder, the
     * cooldown, and every episode marker. `$keepStreaks` keeps the failure counters for idle
     * promotion, where nothing was delivered to prove health. Returns true when a row actually
     * transitioned.
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
     * Mirrors the legacy `webhook.active`/`error_count` columns from the CURRENT health row in
     * one JOINed write. Because the values come from the row itself, a mirror that runs late can
     * never write a stale state (e.g. a DEGRADED mirror overwriting a concurrent SUSPEND). Per
     * the ADR BC table: active = HEALTHY or DEGRADED; HEALTHY mirrors error_count = 0 (even when
     * idle promotion kept the internal streaks); every other state mirrors the larger of the two
     * streaks — GREATEST(transient, non-transient) — so an auth-suspended endpoint whose
     * transient counter never moved still reports a non-zero error_count. Touches this webhook
     * only — no RelatedWebhooks sibling propagation.
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
     * Best-effort emission after the transition is already committed: a throwing listener must
     * not bubble into the delivery path. The events are advisory; the row is the truth.
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

    private function appIdOf(string $webhookId): ?string
    {
        $appId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(app_id)) FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        return \is_string($appId) ? $appId : null;
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

        // Fail-open: a missing health row reads as HEALTHY, so dispatch and recovery are never
        // silently blocked.
        return $state === false ? EndpointState::Healthy : EndpointState::from((string) $state);
    }

    private function now(): string
    {
        return $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
