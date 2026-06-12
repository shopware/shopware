---
title: Webhook endpoint-health rework — v6.8.0 legacy removal runbook
date: 2026-06-05
area: framework
issue: 16565
status: staged (not executed)
---

# Webhook endpoint-health rework — v6.8.0 removal runbook

The endpoint-health rework (#16565, see `adr/2026-05-28-webhooks-health-remodel.md`) ships behind the
major-version flag `WEBHOOKS_REWORK`. Until that flag is removed, the pre-rework failure chain
(`RetryWebhookMessageFailedSubscriber` → `WebhookFailureStrategy::DisableOnThreshold` → `RelatedWebhooks`,
plus the `webhook.active`/`error_count` columns) **stays live and byte-equivalent under flag-off**. Deleting
any link before the flag is removed breaks the flag-off contract.

This runbook is the authoritative removal list for the v6.8.0 cutover. It is **staged, not executed** — do
not run it in any 6.8.x line.

## Precondition

Execute only when **all** hold:

1. `WEBHOOKS_REWORK` has been default-on in `trunk` for **≥ 1 minor** (so flag-off is no longer a supported
   runtime path).
2. The removal lands in **v6.8.0** — the rework itself ships in 6.7.x (its migrations live in
   `src/Core/Migration/V6_7`); the legacy chain and the `webhook.active`/`error_count` columns retire one
   release later, in 6.8.0, when `WEBHOOKS_REWORK` is removed.
3. `composer phpstan` and the full webhook suite are green on the branch you start from.

## What gets removed (the legacy chain)

The `@deprecated tag:v6.8.0` annotations mark most of this; the `WebhookFailureStrategy` enum is **not**
annotated (Shopware's phpstan deprecation-ignore does not cover enums, and it is still the flag-off default
strategy), so this list — not the annotations — is the source of truth.

| Symbol | File |
|:---|:---|
| `RetryWebhookMessageFailedSubscriber` (whole class + DI registration) | `Subscriber/RetryWebhookMessageFailedSubscriber.php`, `DependencyInjection/webhook.xml` |
| `RelatedWebhooks` (whole class + DI registration) | `Service/RelatedWebhooks.php`, `webhook.xml` |
| `WebhookFailureStrategy` enum (both cases) + the `shopware.webhook.failure_strategy` config | `WebhookFailureStrategy.php`, `DependencyInjection/Configuration.php`, `webhook.xml` arg on the two delivery services |
| `WebhookHealthService::recordLegacyFailure()` and `resetErrorCount()` | `Service/WebhookHealthService.php` |
| `WebhookEntity::$active`/`$errorCount` + `isActive`/`setActive`/`getErrorCount`/`setErrorCount` | `WebhookEntity.php` |
| `WebhookDefinition` `active` + `error_count` fields + their `getDefaults()` entries | `WebhookDefinition.php` |
| The BC-mirror writes onto `webhook.active`/`error_count` | `Service/WebhookHealthService.php` (`mirrorBcColumns`) |
| `ReactivateWebhookOnActivationSubscriber` (the `active = true` DAL write → reactivate gesture) + `WebhookHealthService::reactivateOnActiveFlip` | `Subscriber/ReactivateWebhookOnActivationSubscriber.php` — reactivation moves to `POST /api/app-system/webhook/reactivate` and the admin UI |
| `DisableWebhookOnAdminDeactivationSubscriber` (the `active = false` DAL write → kill gesture) + `WebhookHealthService::disableByOperatorOnActiveFlip` | `Subscriber/DisableWebhookOnAdminDeactivationSubscriber.php` — the kill moves to `POST /api/_action/webhook/{id}/deactivate` |
| The `active` / `error_count` columns (DB) | new forward-only migration in `Migration/V6_8` |

### Rollout-compat symbols that retire alongside the chain

These carry their own `@deprecated tag:v6.8.0` and exist only to bridge the rollout; remove them in the
same cutover: `bin/console webhook:drain-to-async` (`Command/WebhookDrainToAsyncCommand.php` — flag-off-only
rollback drain), `WebhookOutboxStore::backfillDelivery()` and `recordInflightOutboxEntry()` (legacy-envelope
and admin-worker bridges), the flag-off arm of `WebhookEventMessageHandler`, and `WebhookEventMessage`'s
rework-envelope detection shims.

## Steps (each ends in a byte-equivalence / green-suite checkpoint)

1. **Remove the flag-off branches.** Delete every `Feature::isActive('WEBHOOKS_REWORK')` guard, keeping the
   flag-on arm. The early-return in `RetryWebhookMessageFailedSubscriber::failed()` and the flag checks in
   `WebhookDeliveryService` / `WebhookEventMessageHandler` / `ReactivateWebhookOnActivationSubscriber` /
   `WebhookHealthController::assertHealthApiEnabled()` collapse to the rework path. *Checkpoint:* grep for
   `WEBHOOKS_REWORK` returns only the flag definition.

2. **Delete the legacy failure chain.** Remove `RetryWebhookMessageFailedSubscriber`, `RelatedWebhooks`,
   `WebhookFailureStrategy`, their DI registrations, the `failure_strategy` config + constructor args, and
   `WebhookHealthService::recordLegacyFailure`/`resetErrorCount`. *Checkpoint:* no references remain;
   `composer phpstan` green.

3. **Remove the flag definition** `WEBHOOKS_REWORK`. *Checkpoint:* `bin/console feature:dump` no longer lists
   it; the suite is green with no flag toggling left in tests.

4. **Drop the BC mirror, then the columns.** Delete `mirrorBcColumns` and the `WebhookEntity` /
   `WebhookDefinition` `active`+`error_count` fields/accessors. Add a forward-only `V6_8` migration dropping
   the two columns. Move the reactivation gesture off the `active` DAL write (delete
   `ReactivateWebhookOnActivationSubscriber`; `POST .../reactivate` is the supported path). *Checkpoint:*
   `/api/webhook` no longer exposes `active`/`errorCount`; health is read only via `endpoint_state`; the
   migration runs additively on a populated DB and the suite is green.

## Rollback: flag-off is not a state-restoring rollback once traffic has flowed

Flag-off is byte-equivalent to trunk only for an installation where `WEBHOOKS_REWORK` was **never**
enabled. After even one flag-on episode the equivalence no longer holds, because the flag-on path has
already rewritten the legacy columns and dropped backlog the legacy path cannot reconstruct:

- `webhook.active` and `webhook.error_count` were last written by the **BC mirror**, not the legacy
  failure chain — `active = state ∈ {healthy, degraded}` and `error_count = GREATEST(transient,
  non-transient streak)` off the current health row (HEALTHY mirrors 0). A formerly-DEGRADED webhook
  therefore resumes on the legacy path reading mirror-authored counters, not values the legacy code wrote.
- Held (`paused`) rows are invisible to the legacy path — run `bin/console webhook:drain-to-async` after
  disabling the flag to re-publish them. Deliveries the flag-on path already **cancelled** (the 24 h grace
  age on held rows, a → DISABLED drop) stay FAILED in `webhook_event_log`; events **shed** while suspended
  were never recorded at all. The legacy rollback drain cannot reconstruct either.

So disabling the flag after traffic has flowed is an **execution-path switch, not a state-restoring
rollback** — it changes which code runs from the next event on, but it does not undo the writes or
replay the deliveries the flag-on path already consumed. Treat a post-traffic flag-off as a forward
operational decision, not a return to the pre-rework state.

## Re-enabling the flag after a flag-off period

The opposite direction is also not symmetric: while the flag was off, the legacy chain wrote
`webhook.active`/`error_count` directly and the health rows went stale — a webhook the legacy path
auto-disabled (`active = 0`) may still carry a HEALTHY health row. On re-enable, the health model reads
fail-open (a HEALTHY/no-row webhook dispatches) but the stale mirror keeps it out of the flag-off-shaped
`active = 1` candidate set until something writes health again. The repair is the operator gesture: admin
`PATCH active = true` (or the reactivate API) heals the mirror and resumes stranded rows idempotently.
Expect a short window of mirror divergence after re-enabling; it self-corrects with traffic.

## Notes

- Steps 1–3 are pure code removal (no schema change) and are independently revertable. Step 4 is the only
  irreversible one (column drop) — run it last, on its own, behind the migration.
- The `webhook_health` table, the four health routes (`/state`, `/reactivate`, `/health-status`,
  `/{id}/deactivate`), the lifecycle business events, and the Admin notifications are **not** part of this
  removal — they are the rework's permanent surface.
