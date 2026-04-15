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

The outbox takes over retry scheduling from Messenger. Delivery still goes through the async transport — the outbox drives *when* to retry, not *how* to consume.

- `PENDING_RETRY` status + `next_retry_at` on `webhook_delivery`. Failed deliveries schedule their next attempt via a fixed lookup table (`5s → 30s → 5min → 30min → 4h`) instead of Messenger's 1s/2s/4s.
- Messenger retry disabled (`max_retries: 0`). The handler catches all failures internally and always ACKs.
- Consumer contract headers: `X-Shopware-Event-Id`, `X-Shopware-Sequence`, `X-Shopware-Attempt`.

### PR 3 — MySQL Receiver and Ordered Delivery

The outbox becomes the queue. Workers consume directly from `webhook_delivery`.

- Partition-scoped stream leasing via `SKIP LOCKED` for insertion-ordered delivery within an app.
- The `async` forwarding is removed. Workers run `messenger:consume webhook`.
- Per-webhook failure isolation replaces the shared `RelatedWebhooks` blast radius.

### Future

- Per-webhook endpoint health states and error classification (transient vs non-transient).
- Observability metrics and admin visibility.
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
- The transport forwards to `async` for now. Removed in PR 3 when the MySQL receiver ships.
- Retry behavior is unchanged from trunk until PR 2.
- Old serialized messages in the queue are consumed normally — `partitionKey` falls back to `appId`.
