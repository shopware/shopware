<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;

/**
 * Webhook-health work that runs off the hot path: the clock-driven duties behind the one
 * WebhookHealthTask tick, plus the app install/update reset. The per-delivery transitions
 * live on {@see EndpointHealth}. Who owns which transition:
 *
 *   HEALTHY  → DEGRADED            EndpointHealth::recordFailure  transient threshold crossed
 *   HEALTHY/DEGRADED → SUSPENDED   EndpointHealth::recordFailure  non-transient streak at the threshold, a 410, or the end of the cooldown schedule
 *   DEGRADED → HEALTHY             EndpointHealth::recordSuccess  trial 2xx (held rows resume, filtered by age) — or idle promotion via tick()
 *   SUSPENDED → DEGRADED           EndpointHealth::recordSuccess  trial 2xx (ladder back to tier 0)
 *   ── this interface ──
 *   SUSPENDED → DISABLED           tick() duty 3                  suspended longer than `max_suspended_days`
 *   any non-HEALTHY → HEALTHY      reactivateForApp               clean slate on app install/update (operator-disabled excluded)
 *   any non-HEALTHY → HEALTHY      reactivate                     explicit reactivation — policy depends on the trigger (echo guard, operator-kill protection)
 *   HEALTHY/DEGRADED → DISABLED    disableByOperatorOnActiveFlip  admin PATCH active=false (echo-guarded; origin operator)
 *
 * @internal
 */
#[Package('framework')]
interface EndpointLifecycle
{
    /**
     * One scheduled pass over every DEGRADED/SUSPENDED webhook. Five duties; each is a
     * cheap indexed per-webhook check in its own short transaction, with no HTTP calls:
     *
     *  1. Releases: for each webhook whose cooldown has passed and that has nothing in
     *     flight, flip its oldest `paused` row to claimable as the trial (DEGRADED and
     *     SUSPENDED alike). Releasing never advances the ladder. The released trial's
     *     *result* does, and only if the cooldown had already passed when the result
     *     landed. That way worker lag cannot push an endpoint to SUSPENDED without real
     *     delivery evidence.
     *  2. Idle promotion: DEGRADED with nothing held and nothing in flight → HEALTHY,
     *     keeping the transient streak (nothing was delivered, so nothing proved the
     *     endpoint healthy). SUSPENDED never idle-promotes.
     *  3. Retirement: SUSPENDED past `suspended_since + max_suspended_days` → DISABLED
     *     (origin `escalation`), cancelling everything still undelivered. Webhooks of
     *     deactivated apps are skipped: their suspension clock is paused, not running.
     *  4. Crash-leftover cleanup: cancel rows that crash recovery re-queued onto a
     *     SUSPENDED webhook. Only the one deliberately released row may be in flight.
     *  5. Stale-hold healing: resume `paused` rows left stranded on a HEALTHY webhook by
     *     a race between the gate and a transition.
     *
     * @return int number of webhooks acted on
     */
    public function tick(): int;

    /**
     * Resets an app's webhooks to HEALTHY on an app install/update. A manual config action
     * replaces exactly what usually broke the endpoint (URL, credentials), so it counts as
     * a clean slate. Resets DEGRADED, SUSPENDED, and DISABLED — except operator-disabled
     * webhooks (`disabled_origin = operator`): a merchant's explicit kill must survive a
     * routine app update. This is the only automatic way back from escalation-DISABLED.
     * No-op if all of the app's webhooks are HEALTHY.
     *
     * @return int number of webhooks reset
     */
    public function reactivateForApp(string $appId): int;

    /**
     * Explicit reset of one webhook to HEALTHY. The policy depends on who is asking
     * ($trigger):
     *
     * - Manual (admin `PATCH active = true`): echo-guarded. It transitions only
     *   SUSPENDED/DISABLED — any `disabled_origin`, because this gesture IS the recovery
     *   for an operator-disabled webhook. A write that just repeats the mirrored value is
     *   a no-op for the state machine.
     * - Automation (AppReset / AppReactivateApi): resets any non-HEALTHY state EXCEPT an
     *   operator-disabled webhook. Automation never undoes a human's deliberate kill.
     *
     * Heals idempotently: on an already-HEALTHY webhook (or one without a health row) the
     * BC mirror and the held-row resume still run. So a crash-stranded `paused` backlog or
     * a drifted legacy `active`/`error_count` is repaired even when no state transition
     * happens.
     *
     * @return int 1 when a non-HEALTHY webhook was reset, 0 otherwise
     */
    public function reactivate(string $webhookId, WebhookActivationTrigger $trigger): int;

    /**
     * Handles the admin `PATCH active = false` gesture, echo-guarded. Transitions
     * HEALTHY/DEGRADED → DISABLED with `disabled_origin = operator` and cancels everything
     * still undelivered.
     *
     * On SUSPENDED/DISABLED the mirrored value is already false, so the write is just an
     * echo and a no-op (the unambiguous gesture there is {@see disableByOperator}). A
     * webhook without a health row is inserted as DISABLED, so a delivery that was
     * enqueued but not yet delivered cannot flip `active` back to 1.
     *
     * Operator-disabled webhooks are excluded from every automatic recovery path. Only the
     * operator gesture ({@see reactivate} with the Manual trigger) reverses them.
     *
     * @return int 1 when a webhook was disabled, 0 otherwise
     */
    public function disableByOperatorOnActiveFlip(string $webhookId): int;

    /**
     * The dedicated admin deactivate action: the operator kill switch that works in ANY
     * state. It covers what `PATCH active = false` cannot express on a SUSPENDED/DISABLED
     * webhook, whose mirrored value is already false. Same effect as the flip gesture. On
     * an already-DISABLED webhook it upgrades `disabled_origin` to operator, so automation
     * can no longer revive it.
     *
     * @return int 1 when a webhook transitioned to DISABLED, 0 otherwise
     */
    public function disableByOperator(string $webhookId): int;

    /**
     * Marks the start of an app-deactivation pause for the app's SUSPENDED webhooks. A
     * deactivated app's events never reach the gate, so its webhooks get no trials. The
     * 7-day clock therefore pauses instead of running: {@see tick()} shifts
     * `suspended_since` forward while the app stays deactivated, and the retirement sweep
     * skips it.
     */
    public function pauseSuspensionClockForApp(string $appId): void;
}
