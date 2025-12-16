---
title: Webhook correlation
date: 2025-12-10
area: core
tags: [core, webhooks, app-system, developer-experience]
---

## Context

Shopware dispatches webhooks to apps and external systems to notify them when certain events happen.
The webhook system is built on top of symfonys event dispatching system and allows dispatching certain events as webhooks over HTTP to registered webhook URLs.

When an event is dispatched, Shopware creates a webhook event log entry containing information about the event, the payload, and the target URL.

It might happen that multiple events are triggered in a single request and are thus correlated. However on the webhook receiver side it is hard to figure out that correlation as the webhooks arrive independantly.
For symfony events the correlation is not relevant, as they happen synchronously in the same request context.
To improve the developer experience, we want to add correlation information to webhook events, this includes the same `correlationId` for all webhooks that originate from the same request, as well as `correlationSequence` to identify the order of webhooks in a request.

## Decision

We add correlation information to webhook events. This means we add the following properties to the `source` of the webhook payload:
```
"correlationId": "tx_abc987",  // Shared by all webhooks being dispatched in a single request
"correlationSeq": 1,           // This is the 2nd event (0-indexed)
"correlationCount": 3          // Total of 3 events in this transaction
```

### Sequence ordering

The source events might not be dispatched internally in the same order as we want to dispatch them to the webhooks.
In general, we want to dispatch `BusinessEvents` first as they are the most relevant ones, and we want to dispatch the `EntityWrittenEvent` after the `BusinessEvent`, that actually triggered the write-operation.
Additionally, a single write-operation to the DAL might trigger multiple events, e.g. for the `customer` and the `customer_address` entity. Currently, the `EntityWrittenContainerEvent` contains all the events in the order they were written to the DB, which means the association and foreign key structure determine the order..
That is not ideal as most of the time the primary write-operation (the `root` entity for the write-operation) is the most relevant, therefore, we want to dispatch that event first, followed by the other events in the WrittenContainer event.


## Consequences

When we capture the source event of a webhook, we cannot directly dispatch as we do not have the necessary correlation information. 
That means we need to store the captured events in a different state in the log table and enrich them with the correlation information as soon as the request finishes (and we don't get any more new events that might have been dispatched in the meantime).
