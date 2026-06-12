<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;

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
 *   any non-HEALTHY → HEALTHY      reactivate                     explicit per-webhook reactivation (app API / dedicated action)
 *   SUSPENDED/DISABLED → HEALTHY   reactivateOnActiveFlip         admin PATCH active=true (echo-guarded — only a value flip carries intent)
 *   HEALTHY/DEGRADED → DISABLED    disableByOperator              admin PATCH active=false (echo-guarded; origin operator)
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

    /**
     * Manual per-webhook reset to HEALTHY — the operator gesture (admin `PATCH active = true`) or
     * the app's self-service API; $trigger says which. Heals idempotently: on an already-HEALTHY
     * webhook (or one without a health row) the BC mirror and the held-row resume still run, so a
     * crash-stranded `paused` backlog or a drifted legacy `active`/`error_count` is repaired even
     * when no state transition happens.
     *
     * @return int 1 when a non-HEALTHY webhook was reset, 0 otherwise
     */
    public function reactivate(string $webhookId, WebhookActivationTrigger $trigger): int;

    /**
     * The admin `PATCH active = true` gesture, echo-guarded: only a write that actually flips the
     * mirrored value carries intent, so the reset runs only from SUSPENDED/DISABLED (any
     * `disabled_origin` — this gesture is the operator-disabled recovery). On HEALTHY/DEGRADED the
     * write is an echo and is a no-op for the state machine; the idempotent heal (mirror repair,
     * stranded-hold resume) still runs for HEALTHY rows.
     *
     * @return int 1 when a webhook was reset, 0 otherwise
     */
    public function reactivateOnActiveFlip(string $webhookId): int;

    /**
     * The admin `PATCH active = false` gesture, echo-guarded: transitions HEALTHY/DEGRADED →
     * DISABLED with `disabled_origin = operator` and cancels everything still undelivered. On
     * SUSPENDED/DISABLED the mirrored value is already false, so the write is an echo — a no-op
     * (the unambiguous gesture there is the dedicated deactivate action). A webhook without a
     * health row is inserted DISABLED, so an enqueued-but-undelivered delivery cannot resurrect
     * `active = 1`. Operator-disabled webhooks are excluded from every automatic recovery path;
     * only {@see reactivateOnActiveFlip} reverses them.
     *
     * @return int 1 when a webhook was disabled, 0 otherwise
     */
    public function disableByOperator(string $webhookId): int;

    /**
     * Marks the start of an app-deactivation pause for the app's SUSPENDED webhooks: a deactivated
     * app's events never reach the gate, so its webhooks get no trials — the 7-day clock pauses
     * instead of running ({@see tick()} shifts `suspended_since` forward while the app stays
     * deactivated, and the retirement sweep skips it).
     */
    public function pauseSuspensionClockForApp(string $appId): void;
}
