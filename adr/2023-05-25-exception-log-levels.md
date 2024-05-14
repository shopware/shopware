---
title: Exception Log Level configuration
date: 2023-05-25
area: core
tags: [core, devops, observability]
---

## Context
By default, every exception that is thrown in the PHP stack and not caught will be logged by the `symfony/monolog-bridge` on `error` level.
But there are some cases where the exception is caused by clients accessing the API wrong (missing fields etc.) and throwing an `ShopwareHttpException` with an HTTP-Status-Code of 40x is our way of handling such situations and returning a correct HTTP-Status-Code to the client.
So those cases are in fact no "errors" that need to be analyzed, but are expected given a malformed API request.
Logging those cases as "errors" produces a lot of noise, which makes it harder to actually find errors in the logs.

For our cloud product, we already used a configuration list that configures that some Exception classes should only be logged as notices.

## Decision

We add a configuration to the platform that degrades the error level of specific exceptions to notices. This way, external hosters can also profit from our classification.
We use [symfony's `exceptions` configuration](https://symfony.com/doc/current/reference/configuration/framework.html#exceptions) for this.

This has the benefit that for specific projects this configuration could be adjusted, for example, for cases where you also control all clients that access the shop, you may want to log also every client error, just to help debugging the client.

Another solution could be to do the configuration of the log level directly in the exception class either by attributes or a separate method specifying the log level, but that would make overwriting it for a specific project harder, so we stick to the default symfony configuration.

## Consequences

We will add the `exceptions` configuration to the platform, that way the error logging in existing projects might change. But in general, we assume that this change is for the better.

Additionally, we will need to extend on the default symfony configuration as that is not compatible with our new [domain exceptions](./2022-02-24-domain-exceptions.md) as there are multiple exception cases in one file/class. 
Therefore, we will add a similar configuration option, that does not rely on the FQCN, but instead we will use the shopware specific `error code` from the shopware exception as that is unique to the exception case.

On a side note, we should be able to get rid of most of the cloud-specific configuration for the exception logging mapping.
