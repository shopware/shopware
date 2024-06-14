---
title: Error-code log Level configuration in platform or cloud
date: 2024-06-17
area: core
tags: [core, devops, observability]
---

## Context
In the configuration files for platform and cloud, specific error codes can be set to the notice level.
Some time ago, we decided to configure this in platform ([Exception Log Level configuration](2023-05-25-exception-log-levels.md)).

As it is still essential for some errors to be logged at the highest level for customers with own servers, we now have to decide which errors we can decrease for all customers and which only for cloud. The key consideration is whether it makes sense for on-premise customers to continue logging these errors at a high level. If it does, the error codes must be added to the cloud configuration file in the SaaS template.

For example, an incorrectly configured flow on the customer side is not an error that needs to be analyzed by us and has to be recorded by our error monitoring, but it is important for the customer to be informed about it at the highest log level.

## Decision

We have to decide for each error code whether it makes sense for on-premise customers to continue logging these errors at a high level. If so, the error codes have to be added to the cloud configuration file in the SaaS template.

### This could be a small guide for the decision:
* Never decrease critical errors in platform

Errors that shall be configured in cloud:
* all the unexpected stuff that should not happen and a dev should look at this, even though the fix is not in Shopware itself but probably in some calling code/configuration
* like API misuses
* or misconfigurations on the customer side

Errors that shall be configured in platform:
* all the expected stuff, it is totally normal that those things happen and no dev needs to change something
* like 404 errors
* or invalid user credentials at login


## Consequences

By implementing this approach, we ensure that critical errors are properly logged and monitored in both on-premise and cloud environments, aligning with the needs and contexts of different customer bases.
