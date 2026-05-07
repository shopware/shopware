---
title: Webhook outbox transport
date: 2026-04-14
area: framework
tags: [webhook, outbox, transport, messenger]
---

## Context

Webhook delivery has two reliability gaps:

1. **Synchronous deliveries are invisible.** When `isAdminWorkerEnabled` is true or during app lifecycle events, webhooks fire via Guzzle Pool with no persistence. Failures are silently lost.

2. **Shared queue, shared fate.** Async webhook messages share the `async` Messenger transport with everything else — retry timing, failure handling, and queue depth are not webhook-specific.

## Decision

Introduce an outbox-first persistence layer and a dedicated Messenger transport for webhooks, shipped in incremental PRs.

### PR 1 — Outbox Foundation

Every webhook delivery (sync and async) is now persisted to `webhook_event_log` + `webhook_delivery` before the first HTTP attempt. A dedicated `shopware-webhook://` transport handles persistence, then forwards to `async` for worker consumption (transitional — removed in PR 3).

Key decisions:
- **Partition key** using `xxh128` into `BINARY(16)`. Fast, uniform distribution, and fixed-width binary is compact and index-friendly.
- **Completed deliveries are deleted** from `webhook_delivery` immediately. This keeps the hot table small. The `webhook_event_log` row keeps the full audit trail.
- **Idempotent insertion** via unique constraint on `webhook_delivery.webhook_event_log_id`. The Doctrine transport is at-least-once: a worker crash before `ack()` causes the same message to be consumed again. The constraint makes the second insert a no-op.

  > The Doctrine async forwarding is transitional — PR 3 replaces it with outbox-owned consumption (`messenger:consume webhook`), at which point this at-least-once concern shifts to the outbox's own lease/ack mechanism.
- **No behavioral change** from trunk: Messenger still owns retries, failure strategy unchanged.

### PR 2 — Outbox-Owned Simple Retries

Retry scaffolding: the outbox records retry scheduling. Delivery still goes through the async transport — the outbox drives *when* to retry; consumption of due retries is deferred to PR 3.

- `PENDING_RETRY` status + `next_retry_at` on `webhook_delivery`. Failed deliveries schedule their next attempt via a fixed lookup table (`5s → 30s → 5min → 30min → 4h`) instead of Messenger's 1s/2s/4s.
- `RetryDelayCalculator` computes retry delays from a fixed lookup table. Max-retry decision owned by `WebhookDeliveryService`.
- Feature-flagged via `WEBHOOKS_REWORK` (Shopware Feature flag, default off). Flag ON: handler catches all failures internally, marks `PENDING_RETRY` with `next_retry_at`, always ACKs. Flag OFF: existing Messenger retry behavior.
- Messenger retry disabled on the `webhook` transport (`max_retries: 0`).
- Failure strategy consistent with trunk: on terminal failure, `error_count` is incremented via `RelatedWebhooks` (propagates to webhooks with same event+URL). Per-webhook isolation deferred to Phase 2 health model.
- Sync path (admin worker / app lifecycle): failures mark `PENDING_RETRY` when flag ON. App lifecycle events (`$forceSynchronous`) are delivered synchronously to preserve race-condition prevention semantics (deprecated, removed in v6.8.0). `PENDING_RETRY` rows written by the sync path are not yet consumed — PR 3 (stream leasing) will add the receiver.

  > **Not in this PR:** Transport-level consumption of due retries (`WebhookTransport.get()`), consumer contract headers (`X-Shopware-Event-Id`, `X-Shopware-Sequence`, `X-Shopware-Attempt`), and stream leasing. These ship in PR 3.

### PR 3a — Stream-Leasing Scaffolding

Schema and lock primitives for PR 3b. Migration runs unconditionally; `WEBHOOKS_REWORK` gating from PR 2 is unchanged. `WebhookTransport::get()` still returns `[]` and `StreamLockService` has no behavioral callers, so the `webhook` transport keeps forwarding to `async`.

The migration backfills `webhook_stream` from existing `webhook_delivery.partition_key` values. PR 3b (or enabling `WEBHOOKS_REWORK`) then doesn't need new events to arrive before existing partitions become claimable.

- `webhook_stream` table: UUID PK, `UNIQUE partition_key`, index on `last_claimed_at`. Backfill uses `MultiInsertQueryQueue` and preserves partition-key bytes.
- `StreamLockService`: `claimNext` / `heartbeat` / `release` / `deleteOrphanedStreams` (SKIP LOCKED, 60s orphan grace). `WebhookCleanup` calls the last in a batched loop.
- `OutboxEventRepository::fetchDue()`: due `QUEUED` / `PENDING_RETRY` rows scoped to a claimed partition. Statuses are PHPStan-narrowed to literals — no runtime validation.
- `webhook_stream` added to `DefinitionValidator::TABLES_WITHOUT_DEFINITION` (DBAL-managed, no DAL entity).

### PR 3b — MySQL Receiver, Retry Consumption, and Ordered Delivery

The outbox becomes the queue. With `WEBHOOKS_REWORK` active, workers consume directly from `webhook_delivery` via stream-leased polling. With the flag inactive, newly dispatched messages still forward to `async`; rows already created while the flag was active are MySQL-outbox owned and must be drained before disabling the flag.

- `MySQLWebhookReceiver` drives consumption via `StreamLockService` — claim a partition (`SKIP LOCKED`), run crash-recovery on stale `RUNNING` rows, yield every due entry in insertion order, rotate on batch or lease-age budget.
- Blob ↔ row identity contract: before yielding, the receiver asserts that the deserialized `WebhookEventMessage`'s `webhookEventId` matches the leased row's `webhook_event_log_id`. A mismatch (migration-corrupted blob) is dropped via `dropBrokenEntry` rather than allowed to mutate a different event's state. Fresh URL / secret sourcing from the webhook row at delivery time is follow-up work — see `adr/webhooks/07-implementation-notes.md`.
- `reject()` is a no-op for webhook messages: the row stays `RUNNING` and stale-attempt recovery returns it to `PENDING_RETRY` on a later partition claim.
- Consumer contract is emitted by a single seam (`WebhookDeliveryService::buildRequest`) regardless of dispatch path: `X-Shopware-Event-Id`, `X-Shopware-Sequence`, `X-Shopware-Attempt` (0-indexed), plus `source.sequence` in the JSON payload.
- `WebhookTransport` flag-gates its full lifecycle. Flag OFF: `send` persists and forwards to `async`, `get` returns `[]`. Flag ON: `send` persists only, `get` delegates to the receiver.
- Claim and terminal-write correctness is attempt-owned: due retries cannot be claimed before `next_retry_at`, and HTTP result writers can fence updates by `webhook_delivery.id` plus `execution_count` so stale workers cannot delete or rewrite a newer attempt.
- Rollout glue: `WebhookConsumeMessagesSubscriber` (`@deprecated tag:v6.8.0`) prepends `webhook` before `async` when `messenger:consume` is invoked with `async` in the receiver list. Operators running the default `messenger:consume async` (or `async` plus priority queues) pick up webhooks without changing their command. Specialty workers (`messenger:consume failed`, `messenger:consume low_priority`) are left untouched — they were explicitly scoped to a different transport, and the subscriber must not silently widen them onto `webhook`. Removed in v6.8 in favour of explicit receiver configuration.
- `--queues=X` interaction — deliberate loud failure: the subscriber does not special-case queue-filtered commands. An operator running `messenger:consume async --queues=X` will get Symfony's own `RuntimeException` at worker startup ("Receiver for 'webhook' does not implement QueueReceiverInterface") because `WebhookTransport` is not a `QueueReceiverInterface`. Under `WEBHOOKS_REWORK`, `WebhookTransport::send` also stops forwarding to `async`, so that same operator's command would silently drain zero webhooks regardless. The crash is the right signal: add a dedicated `messenger:consume webhook` worker. Silently skipping the prepend (earlier design) was rejected because it hides the real problem — webhooks piling up in `webhook_delivery` behind a healthy-looking worker.

### PR 5 — Observability And Runtime Metrics

Before production rollout, add first-class metrics through Shopware's telemetry
metrics layer (`Meter` + `ConfiguredMetric`) rather than bespoke counters.

PR5 scope:

- metric definitions for every emitted webhook metric; dev/test must fail on
  undefined metrics through the existing `MetricConfigProvider` guard
- a small webhook metrics helper to centralize metric names and bounded labels
- emitters for first arrivals, retries, terminal outcomes, claim/fetch latency,
  batch size, stale resets, lease loss, cleanup deletes, and worker contention
- a scheduled collector for hot-queue depth and oldest due age by bounded status
- dashboards/alerts for oldest due age, retry storms, terminal failure growth,
  stale reset spikes, lease loss, and worker starvation

See `adr/webhooks/FUTURE-METRICS.md` for the proposed metric names and labels.

### PR 6 — Rollback And Flag-Off Drain Tooling

Documented future work, not implemented in this PR.

The unsupported scenario is: enable `WEBHOOKS_REWORK`, create a large MySQL
outbox backlog, then disable the flag while rows remain in `webhook_delivery`.
Flag-off workers do not poll the MySQL webhook outbox, so those rows must not be
expected to drain through the legacy Redis/shared `async` path automatically.

PR6 should add one explicit rollback contract:

- drain-before-disable guard and operator check
- or a requeue tool that converts due MySQL outbox rows back into legacy async
  messages without losing event ids
- or dual-read/dual-drain support for a bounded rollback window

The E2E acceptance test should create several thousand flag-on rows, flip the
flag off before the queue is empty, and prove the chosen rollback path drains
without loss or duplicate first attempts.

### Future

- Per-webhook endpoint health states and error classification (transient vs non-transient).
- Admin visibility for delivery health and replay/backlog inspection.
- External FIFO transport adapters (SQS FIFO, Kafka).

## Schema

### `webhook_delivery` (new)

| Column | Type | Notes |
|:---|:---|:---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | PK and sequence source |
| `webhook_event_log_id` | `BINARY(16) NOT NULL UNIQUE` | FK to audit log |
| `webhook_id` | `BINARY(16) NULL` | FK to webhook (SET NULL on delete) |
| `partition_key` | `BINARY(16) NOT NULL` | For stream leasing |
| `delivery_status` | `VARCHAR(20) DEFAULT 'queued'` | State machine |
| `execution_count` | `INT UNSIGNED DEFAULT 0` | Attempt counter |
| `next_retry_at` | `DATETIME(3) NULL` | PR 2: outbox-owned retry timing |
| `last_attempt_at` | `DATETIME(3) NULL` | Last delivery attempt |
| `created_at` | `DATETIME(3) NOT NULL` | |

### `webhook_event_log` (extended)

Added `sequence BIGINT UNSIGNED NULL` — back-reference to `webhook_delivery.id`.

## Consequences

- Every webhook delivery has an audit trail, including the previously invisible sync path.
- Flag ON: the MySQL outbox owns lifecycle, retry timing, stream leasing, and terminal cleanup.
- Flag OFF: newly dispatched messages are still forwarded to `async` for legacy behavior. Rows already created while the flag was ON are not forwarded retroactively; drain `webhook_delivery` before disabling the flag, or use a future rollback tool.
- Default admin-worker transports include `webhook` before `async`, so inline failures that schedule `PENDING_RETRY` are picked up by the outbox receiver.
- Old serialized messages in the queue are consumed normally: legacy messages can be backfilled, and `partitionKey` falls back to `appId`.
