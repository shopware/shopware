---
title: Change flow execution to be asynchronous
date: 2025-10-22
area: Framework and CRM & After Sales
tags: [flow, flow-action, experimental]
---

## Context

The Flow Builder is a central automation feature in Shopware. Today, flows are executed inside the business process, inside an HTTP request.
A “business process” refers to the synchronous domain transaction triggered by a user action such as a checkout (order creation, payment handling, stock update).
This synchronous design introduces two key problems:

- Failures in flow execution can directly interrupt the main business logic. For example, out of memory errors or long-running tasks like email sending may cause the checkout HTTP request itself to fail
- Even when successful, flows add latency to critical user-facing actions. Business processes become slower as they wait for non-essential side effects to complete.

## Decision

Flow execution will be moved asynchronously into the message queue.
During the business process, dedicated transport dispatches flows for background handling.
This transport remains configurable: in special cases, it can be set to synchronous processing to restore the current behavior,
but the default will be async.

The change will be introduced behind the feature flag `FLOW_EXECUTION_AFTER_BUSINESS_PROCESS`.
For Shopware 6.7 it remains opt-in; from Shopware 6.8 onward it becomes the default.

## Consequences

The main effect is isolation:
business processes finish quickly and cannot be blocked by flow logic.
Performance improves, and failures inside flows no longer jeopardize order placement or similar operations.

However, execution is no longer immediate.
The outcome of a flow, such as order status changes or sending a confirmation email, depends on queue throughput and consumer availability.
Under load, this may mean noticeable delay. Extensions that assumed in-request execution need to be adapted.
All flow actions must therefore be strictly independent of request context and only rely on the specific event data provided to them,
which should already be the case for all default flow actions.

The following table highlights the trade-off:

| Aspect                | Synchronous (old)                | Asynchronous (new)                |
| --------------------- | -------------------------------- | --------------------------------- |
| Request latency       | Higher, includes flow runtime    | Lower, flow runs outside request  |
| Failure impact        | Can break checkout or order flow | Contained in async queue handling |
| Timing guarantees     | Immediate within request         | Eventual, depends on queue speed  |
| Extension assumptions | May rely on sync state           | Must tolerate delayed execution   |

Additionally, see these visualizations of the execution:

```text
CURRENT (synchronous)

User -> [Checkout Request]  
           |  
           v  
   [Business Process]  
           |  
           v  
 [Flow Execution (inline)]  
           |  
           v  
 [Response returned]   <-- response AFTER flows

(flows run inside the request and can block/fail it)
```

```text
PROPOSED (asynchronous)

User -> [Checkout Request]
           |
           v
   [Business Process]
           |
           v
     [Enqueue to MQ] --------> [Consumer executes later in a different process]
           |
           v
 [Response returned immediately]   <-- response BEFORE flows

(flows run after the response; isolated in the queue)
```

### Technical insights

A lot of preparation to enable this change is already done. 
We have the concept of `StoreableFlow` and it's already used by the Delayed flows commercial feature.

The flow storer mechanism extracts and serializes only the minimal, replayable bits of a flow’s execution context.
When the flow is triggered, we enqueue that compact StorableFlow.
Message consumers later restore the context and run the flow.
This is what makes delayed / async flows practical and safe.

More details on this can be found in our documentation: https://developer.shopware.com/docs/concepts/framework/flow-concept.html#storer-concept

## Alternatives Considered

Partial async: 
Only some flow actions run asynchronously.
This creates complexity: ordering cannot be guaranteed, and failures in sync actions can still break business processes.

Post-process in request:
Running flows after business logic but still inside the request (see [2025-01-31-move-flow-execution-after-business-process.md](https://github.com/shopware/shopware/blob/trunk/adr/_superseded/2025-01-31-move-flow-execution-after-business-process.md)).
It isolates them from transactions but still adds overhead and failure risk.

## Rollout & Migration

During 6.7, projects can enable the `FLOW_EXECUTION_AFTER_BUSINESS_PROCESS` feature flag to test async execution and validate extensions.
Please report issues you encounter with the new execution model during this time.

In 6.8, the implementation will be enabled by default and the feature flag removed.
Operators requiring strict sync execution can switch the flow transport back to synchronous via configuration,
but are encouraged to adapt their environment to the new model.
