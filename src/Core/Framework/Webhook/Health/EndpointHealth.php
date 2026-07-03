<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * Per-message webhook health. The delivery hot path asks this interface how to dispatch
 * each event and reports each delivery result. The implementation owns the `webhook_health`
 * table and holds deliveries back via the `paused` delivery status. The transport knows
 * nothing about health.
 *
 * This interface owns the per-delivery state changes:
 * - recordFailure moves HEALTHY → DEGRADED (transient-failure threshold crossed) and
 *   → SUSPENDED (a non-transient streak at its threshold, or a 410 right away).
 * - recordSuccess climbs exactly one state per 2xx: DEGRADED → HEALTHY and
 *   SUSPENDED → DEGRADED.
 *
 * Both non-HEALTHY states recover through the same half-open ladder. The health task
 * releases the oldest held row as the trial. Only a SUSPENDED webhook with nothing held
 * gets its trial from natural traffic instead, admitted here by {@see gateFor}. Everything
 * clock-driven (releases, idle promotion, the time limit for SUSPENDED → DISABLED,
 * cleanup) lives on {@see EndpointLifecycle}.
 *
 * @internal
 */
#[Package('framework')]
interface EndpointHealth
{
    /**
     * Decides how to dispatch one event for one webhook: deliver (claimable row), hold
     * (paused row), or skip (no row). See {@see WebhookDispatchDecision}.
     *
     * HEALTHY delivers. DEGRADED holds. SUSPENDED and DISABLED skip — with one exception:
     * a SUSPENDED webhook whose cooldown has passed, with nothing held and nothing in
     * flight, gets exactly one delivery through as the half-open trial. A guarded lease
     * re-arm keeps that to one per burst. Admitting the trial counts the ladder step;
     * its result does not count again.
     */
    public function gateFor(string $webhookId): WebhookDispatchDecision;

    /**
     * Records a 2xx delivery. It climbs exactly one state: DEGRADED → HEALTHY (held rows
     * resume, filtered by age), or SUSPENDED → DEGRADED (ladder back to tier 0;
     * `suspended_since` is kept — HEALTHY must then be earned through the same ladder).
     * Resets both failure streaks and clears the cooldown.
     */
    public function recordSuccess(string $webhookId): void;

    /**
     * Records one failed delivery attempt and returns the webhook's resulting state. The
     * caller uses that state to place the in-flight row (re-hold / fail / retry).
     *
     * This is called per attempt, but health counts per delivery. One delivery's whole
     * retry ladder counts once toward `degraded_threshold`, never once per attempt. The
     * non-transient streak also moves once per delivery (a non-transient result ends the
     * delivery, so there can be at most one). Any 2xx resets that streak; transient
     * failures neither advance nor reset it. Payload failures never touch health.
     *
     * Ladder accounting: only released trials advance the ladder, only via their result,
     * and only if the cooldown had already passed when the result landed. Stragglers and
     * crash-recovered rows are re-held without counting.
     */
    public function recordFailure(string $webhookId, ErrorClassification $classification, int $attempt): EndpointState;
}
