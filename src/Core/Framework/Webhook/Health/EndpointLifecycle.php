<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * Off-hot-path webhook-health orchestration: the clocked duties driven by the one WebhookHealthTask
 * tick, plus the app-install/update reset. The per-delivery transitions live on
 * {@see EndpointHealth}. Full ownership map:
 *
 *   HEALTHY  → DEGRADED            EndpointHealth::recordFailure  transient threshold crossed
 *   HEALTHY/DEGRADED → SUSPENDED   EndpointHealth::recordFailure  non-transient streak at the threshold, a 410, or the schedule's end
 *   DEGRADED → HEALTHY             EndpointHealth::recordSuccess  trial 2xx (resumes held rows, age-filtered) — or idle promotion via tick()
 *   SUSPENDED → DEGRADED           EndpointHealth::recordSuccess  trial 2xx (ladder reset to tier 0)
 *   ── this interface ──
 *   SUSPENDED → DISABLED           tick() duty 3                  held past `max_suspended_days`
 *   any non-HEALTHY → HEALTHY      reactivateForApp               app install/update clean slate (operator-disabled excluded)
 *
 * Manual per-webhook reactivation (any → HEALTHY) is owned by the reactivation API, not this interface.
 *
 * @internal
 */
#[Package('framework')]
interface EndpointLifecycle
{
    /**
     * One scheduled tick over every DEGRADED/SUSPENDED webhook — five duties, each a cheap indexed
     * per-webhook check in its own short transaction, no HTTP:
     *
     *  1. Releases: per webhook with an elapsed cooldown and nothing in flight, flip the oldest
     *     `paused` row claimable as the trial (DEGRADED and SUSPENDED alike). Releasing never
     *     advances the ladder — the released trial's *result* does, and only when the cooldown was
     *     already elapsed as the result landed, so worker lag can't march an endpoint to SUSPENDED
     *     without delivery evidence.
     *  2. Idle promotion: DEGRADED with nothing held and nothing in flight → HEALTHY, keeping the
     *     transient streak (nothing was delivered, so nothing proved health). SUSPENDED never
     *     idle-promotes.
     *  3. Retirement: SUSPENDED past `suspended_since + max_suspended_days` → DISABLED (origin
     *     `escalation`), cancelling everything still undelivered. Skips webhooks of deactivated
     *     apps — their suspension clock is paused, not running.
     *  4. Crash-leftover cleanup: cancel rows crash-recovery re-queued onto a SUSPENDED webhook;
     *     only the one deliberately released row may be in flight.
     *  5. Stale-hold healing: resume `paused` rows stranded on a HEALTHY webhook by a
     *     gate/transition race.
     *
     * @return int number of webhooks acted on
     */
    public function tick(): int;

    /**
     * Reset an app's webhooks to HEALTHY on an app install/update — a manual config action replaces
     * exactly what usually broke the endpoint (URL, credentials), so it is a clean slate. Resets
     * DEGRADED/SUSPENDED/DISABLED, except operator-disabled webhooks (`disabled_origin = operator`):
     * a merchant's explicit kill survives a routine app update. This is the only automatic path back
     * from escalation-DISABLED. No-op if all of the app's webhooks are HEALTHY.
     *
     * @return int number of webhooks reset
     */
    public function reactivateForApp(string $appId): int;
}
