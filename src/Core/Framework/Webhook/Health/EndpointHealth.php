<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * Per-message webhook health: the delivery hot path asks how to dispatch each event and reports
 * each delivery outcome. The implementation owns the `webhook_health` table and gates delivery via
 * the `paused` delivery status; the transport stays health-agnostic.
 *
 * Owns the per-delivery transitions: recordFailure drives HEALTHY→DEGRADED (transient threshold)
 * and →SUSPENDED (non-transient streak at its threshold, or a 410 immediately); recordSuccess
 * climbs exactly one state per 2xx — DEGRADED→HEALTHY and SUSPENDED→DEGRADED. Recovery for both
 * non-HEALTHY states rides one half-open ladder: the health task releases the oldest held row as
 * the trial; only a SUSPENDED webhook with nothing held has its trial admitted here, by
 * {@see gateFor}, on natural traffic. The clocked duties (releases, idle promotion, the time-based
 * SUSPENDED → DISABLED bound, cleanup) live on {@see EndpointLifecycle}.
 *
 * @internal
 */
#[Package('framework')]
interface EndpointHealth
{
    /**
     * Dispatch gate for a single event on one webhook: deliver (claimable row), hold (paused row),
     * or skip (no row). See {@see WebhookDispatchDecision}. HEALTHY delivers; DEGRADED holds;
     * SUSPENDED/DISABLED skip — except a SUSPENDED webhook whose cooldown has elapsed with nothing
     * held and nothing in flight, where the gate admits exactly one delivery as the half-open trial
     * (a guarded lease re-arm makes it one per burst; the admission itself counts the ladder, its
     * result does not count again).
     */
    public function gateFor(string $webhookId): WebhookDispatchDecision;

    /**
     * A 2xx delivery climbs exactly one state: DEGRADED → HEALTHY (resuming held rows,
     * age-filtered), SUSPENDED → DEGRADED (ladder reset to tier 0; `suspended_since` preserved —
     * HEALTHY is then earned through the same ladder). Resets both failure streaks and clears the
     * cooldown.
     */
    public function recordSuccess(string $webhookId): void;

    /**
     * Records one failed delivery attempt's classification. Fires per attempt, not per delivery;
     * the implementation aggregates per delivery — one delivery's retry ladder counts once toward
     * `degraded_threshold`, never once per attempt. The non-transient streak likewise advances once
     * per delivery, is reset by any 2xx, and is neither advanced nor reset by transient failures.
     * Payload-class failures never touch health. Ladder/cycle accounting: only released trials
     * advance the ladder, on their result, and only when the cooldown was already elapsed as the
     * result landed — stragglers and crash-recovered rows are re-held without counting.
     */
    public function recordFailure(string $webhookId, ErrorClassification $classification): void;
}
