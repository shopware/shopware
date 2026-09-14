---
title: Webhook endpoint-health rework — v6.8.0 legacy removal runbook
date: 2026-06-05
area: framework
issue: 16565
status: staged (not executed)
tags: [webhook, health, deprecation, migration]
---

# Webhook endpoint-health rework — v6.8.0 removal runbook

The endpoint-health rework (#16565; see `adr/2026-05-28-webhooks-health-remodel.md`) ships behind
`WEBHOOKS_REWORK`. While flag-off remains supported, the legacy failure chain and the
`webhook.active`/`error_count` columns must remain byte-equivalent to trunk. This is the authoritative,
staged removal list for the v6.8.0 cutover; do not execute it earlier.

## Precondition

Execute only when **all** hold:

1. `WEBHOOKS_REWORK` has been default-on in `trunk` for **≥ 1 minor** (so flag-off is no longer a supported
   runtime path).
2. The removal lands in **v6.8.0** — the rework itself ships in 6.7.x (its migrations live in
   `src/Core/Migration/V6_7`); the legacy chain and the `webhook.active`/`error_count` columns retire one
   release later, in 6.8.0, when `WEBHOOKS_REWORK` is removed.
3. `composer phpstan` and the full webhook suite are green on the branch you start from.

## What gets removed (the legacy chain)

The `@deprecated tag:v6.8.0` annotations mark most symbols, not all of them: `WebhookFailureStrategy`
(enum deprecations are not ignored by PHPStan and it stays the flag-off default), the BC-mirror and
lifecycle-subscriber internals, and the `WebhookDefinition` legacy fields are deliberately unannotated —
they stay load-bearing on a live path until the removal itself. **This table, not the annotations, is
authoritative.**

| Symbol | File |
|:---|:---|
| `RetryWebhookMessageFailedSubscriber` (whole class + DI registration) | `Subscriber/RetryWebhookMessageFailedSubscriber.php`, `DependencyInjection/webhook.php` |
| `WebhookFailureStrategy` enum (both cases) + the `shopware.webhook.failure_strategy` config | `WebhookFailureStrategy.php`, `DependencyInjection/Configuration.php`, `webhook.php` arg on the two delivery services |
| `WebhookHealthService::recordLegacyFailure()` and `resetErrorCount()` | `Service/WebhookHealthService.php` |
| `WebhookEntity::$active`/`$errorCount` + `isActive`/`setActive`/`getErrorCount`/`setErrorCount` | `WebhookEntity.php` |
| `WebhookDefinition` `active` + `error_count` fields + their `getDefaults()` entries | `WebhookDefinition.php` |
| The BC-mirror writes onto `webhook.active`/`error_count` | `Service/WebhookHealthService.php` (`mirrorBcColumns`) |
| `ReactivateWebhookOnActivationSubscriber` (the `active = true` DAL write → reactivate gesture) + `WebhookHealthService::reactivate()` (Manual trigger) | `Subscriber/ReactivateWebhookOnActivationSubscriber.php` — reactivation moves to `POST /api/app-system/webhook/reactivate` and the admin UI |
| `DisableWebhookOnAdminDeactivationSubscriber` (the `active = false` DAL write → kill gesture) + `WebhookHealthService::disableByOperatorOnActiveFlip` | `Subscriber/DisableWebhookOnAdminDeactivationSubscriber.php` — the kill moves to `POST /api/_action/webhook/{id}/deactivate` |
| The `active` / `error_count` columns (DB) | new forward-only migration in `Migration/V6_8` |

### Rollout-compat symbols that retire alongside the chain

Also remove the rollout-only `@deprecated tag:v6.8.0` bridges: `bin/console webhook:drain-to-async`
(`Command/WebhookDrainToAsyncCommand.php`, flag-off rollback), `WebhookOutboxStore::backfillDelivery()` and
`recordInflightOutboxEntry()` (legacy-envelope/admin-worker bridges), the flag-off arm of
`WebhookEventMessageHandler`, and `WebhookEventMessage`'s rework-envelope detection shims.

## Steps (each ends in a byte-equivalence / green-suite checkpoint)

1. **Remove the flag-off branches.** Delete every `Feature::isActive('WEBHOOKS_REWORK')` guard, keeping the
   flag-on arm. The early-return in `RetryWebhookMessageFailedSubscriber::failed()` and the flag checks in
   `WebhookDeliveryService` / `WebhookEventMessageHandler` / `ReactivateWebhookOnActivationSubscriber` /
   `WebhookHealthController::assertHealthApiEnabled()` collapse to the rework path. *Checkpoint:* grep for
   `WEBHOOKS_REWORK` returns only the flag definition.

2. **Delete the legacy failure chain.** Remove `RetryWebhookMessageFailedSubscriber`,
   `WebhookFailureStrategy`, their DI registrations, the `failure_strategy` config + constructor args, and
   `WebhookHealthService::recordLegacyFailure`/`resetErrorCount`. *Checkpoint:* no references remain;
   `composer phpstan` green.

3. **Remove the flag definition** `WEBHOOKS_REWORK`. *Checkpoint:* `bin/console feature:dump` no longer lists
   it; the suite is green with no flag toggling left in tests.

4. **Drop the BC mirror, then the columns.** Delete `mirrorBcColumns` and the `WebhookEntity` /
   `WebhookDefinition` `active`+`error_count` fields/accessors. Add a forward-only `V6_8` migration dropping
   the two columns. Before dropping, that migration re-runs the health backfill's only-missing anti-join
   (same statement as `Migration1783617451AddWebhookHealthModel::backfillFromWebhook`): a webhook that was
   auto-disabled during a flag-off 6.7 window has `active = 0` but no health row, and without the re-run the
   column drop would silently re-enable it (the loader's `active = 1` filter dies with the column). Move the
   reactivation gesture off the `active` DAL write (delete
   `ReactivateWebhookOnActivationSubscriber`; `POST .../reactivate` is the supported path). *Checkpoint:*
   `/api/webhook` no longer exposes `active`/`errorCount`, and `GET /api/app-system/webhook/state` drops
   its mirrored `active`/`errorCount` response fields in the same release — a response-shape change for
   app consumers (both fields are schema-required in 6.7, so the static schema fragment changes too);
   health is read only via `endpoint_state`; the migration runs additively on a populated DB and the
   suite is green.

## Rollback: flag-off is not a state-restoring rollback once traffic has flowed

Flag-off is byte-equivalent to trunk only when `WEBHOOKS_REWORK` was **never** enabled. Once flag-on traffic
has flowed, the rework has rewritten legacy columns and may have dropped backlog that the legacy path cannot
reconstruct:

- `webhook.active` and `webhook.error_count` were last written by the **BC mirror**, not the legacy
  failure chain — `active = state ∈ {healthy, degraded}` and `error_count = GREATEST(transient,
  non-transient streak)` off the current health row (HEALTHY mirrors 0). A formerly-DEGRADED webhook
  therefore resumes on the legacy path reading mirror-authored counters, not values the legacy code wrote.
- Held (`paused`) rows are invisible to the legacy path — run `bin/console webhook:drain-to-async` after
  disabling the flag to re-publish them. Deliveries the flag-on path already **cancelled** (the 24 h grace
  age on held rows, a → DISABLED drop) stay FAILED in `webhook_event_log`; events **shed** while suspended
  were never recorded at all. The legacy rollback drain cannot reconstruct either.

After traffic, disabling the flag is an **execution-path switch, not a state-restoring rollback**. It changes
future execution but neither undoes writes nor reconstructs deliveries.

## Re-enabling the flag after a flag-off period

While flag-off, the legacy chain writes `webhook.active`/`error_count` directly and health rows can become
stale. On re-enable, the health model fails open, but a stale `active = 0` mirror can exclude a webhook until
health is written again. Admin `PATCH active = true` or the reactivate API repairs the mirror and resumes
stranded rows idempotently; traffic also self-corrects the temporary divergence.

## Notes

- Steps 1–3 are pure code removal (no schema change) and are independently revertable. Step 4 is the only
  irreversible one (column drop) — run it last, on its own, behind the migration.
- The `webhook_health` table, the four health routes (`/state`, `/reactivate`, `/health-status`,
  `/{id}/deactivate`), the lifecycle business events, and the Admin notifications are **not** part of this
  removal — they are the rework's permanent surface. One carve-out: the `/state` response loses its
  mirrored `active`/`errorCount` fields when the columns drop (step 4's checkpoint).
