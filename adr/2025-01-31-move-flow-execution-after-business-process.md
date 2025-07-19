---
title: Move flow execution after business process
date: 2025-01-31
area: Core
tags: [flow, flow-action, experimental]
---

## Context

Currently, flows are executed during the business process. A business
process is any event that is triggered by the user, like a checkout or
a product update. Flows are used to react to these events and execute user defined actions.

There are several downsides to this approach. The most straightforward is the possibility of a fatal error during
the execution of any flow. This will inevitably cancel the in progress business process. While there are remedies in place
to avoid any trivial errors in flow execution disrupting the business process, certain errors can not be avoided.

Further, even if all flows execute without error, they noticeably impact business process performance. Flows are often 
employed to, for example, send mails, an expensive operation that is not necessary to complete the business process.

In addition to these concrete disadvantages, there are also some more abstract ones. For one, currently flows are executed directly
from a decorator of Symfony's `EventDispatcher`, leading to cumbersome debugging and bloated stack traces. This would
be improved if flows are executed by a dedicated event listener. Additionally moving flows to their own execution
environment would not only simplify debugging, but also potentially make expanding their capabilities easier.

> *The following is all experimental and only takes effect if the associated feature flag `FLOW_EXECUTION_AFTER_BUSINESS_PROCESS` is enabled*

## Decision


We will move the flow execution after the business process. As outlined above we believe that this will simplify the
development around flows, while also improving their performance and safeguarding the main execution environment.

To ensure that flows are executed as close to the business process that triggered them as possible, we will
'queue' the flow execution. This means that flows will be executed after the business process has finished.
Flows are stored in memory and executed as soon as the execution environment signals, that it has
finished a unit of work. The associated events are as follows:

1. After a controller action has been executed (Web) => `KernelEvents::TERMINATE`
2. After a queued message has been processed (Queue) => `WorkerMessageHandledEvent`
3. After a command has been executed (CLI) => `ConsoleEvents::TERMINATE`

Another option would be to handle flow executions as queue messages. This would entirely remove
flow executions from the runtime of the business process. While this would be a simpler solution,
it would both make debugging more complex and introduce an unpredictable delay between the business
process and the flow execution. While this delay could be mitigated by using a high priority queue,
it could not reliably be kept under a certain threshold. To entirely avoid this delay, we decided
that the flow execution should be handled as close to the business process as possible.

## Consequences

1. Flows can no longer fail the business process.
2. The interface for registering flows will not change.
3. Any plugins that rely on flows being executed during the business process will have to be updated.
4. Total execution time is not expected to increase significantly.
5. Business process performance is expected to improve.
