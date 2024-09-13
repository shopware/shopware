---
title: System Health Checks in Shopware
date: 2024-08-02
area: core
tags: [health-check, system, monitoring]
---

## Context

In some instances, a system rollout was completed where an error in certain system functionalities was not detected until the system was live.

A software system is made up of many components that work together to provide a service. The software system can still be healthy even if some of its components are not fully in a healthy state.  System health checks are a way to monitor the health of a system and detect failures early.

## Decision

We will implement system health checks in Shopware to monitor certain parts of the system with the aim to detect failures and issues early.
This system should be extensible and allow for custom health checks to be added with ease.

### Abstractions and Core concepts

The following abstractions and concepts, are core to the implementation:

1. **Shopware\Core\Framework\SystemCheck\BaseCheck**:
    - Defines a base class for all system checks.

2. **Shopware\Core\Framework\Health\Check\Category**:
    - Represents the category of functionality that the check is covering. 
    - Categories:
        - `SYSTEM`: System checks makes sure that the backbone of the software is functioning correctly. Example: Database connection.
        - `FEATURE`: Feature checks make sure that a specific feature of the software is functioning correctly. Example: Payment system.
        - `EXTERNAL`: External checks make sure that external services are responding correctly. Example: SMTP server is online.
        - `AUXILIARY`: Auxiliary checks make sure that auxiliary services are functioning correctly. Example: background tasks are running.

3. **Shopware\Core\Framework\SystemCheck\Check\Result**:
    - Represents the outcome state of a check.

4. **Shopware\Core\Framework\SystemCheck\Check\Status**:
    - Represents the status of a health check result.
    - Statuses (in order of severity):
      - `OK`: The component is functioning correctly.
      - `SKIPPED`: The component check was skipped.
      - `UNKNOWN`: The component status is unknown.
      - `WARNING`: The component is functioning but with some issues that are not errors.
      - `ERROR`: The component has runtime errors, but some parts of it could still be functioning.
      - `FAILURE`: The component has failed with irrecoverable errors.

5. **Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext**:
    - Represents the context in which a health check is executed.
    - Contexts:
        - `WEB`: The check is running in a web environment.
        - `CLI`: The check is running in a command-line interface environment.
        - `PRE_ROLLOUT`: The check is running before a system rollout.
        - `RECURRENT`: The check is running as part of a scheduled task.

#### System Check Guidelines

System checks can differ in complexity, purpose, and computational cost. The types are logical categorizations based on the need and cost for the test and is used to determine the appropriate execution context for a check.
This distinction is primarily reflected in the `Shopware\Core\Framework\SystemCheck\BaseCheck` class method:
```php
    protected function allowedSystemCheckExecutionContexts(): array
    {...}
```

##### Readiness Checks

Readiness checks are intended to be run by infrastructure teams to determine if a system is ready to be rolled out and accept traffic or run scheduled tasks.

Those checks should typically check critical paths of the system, such as correct configuration and if the storefront indices are correctly opening.  There are no requirements for readiness checks to be fast.

Those system checks would have:

```php
    protected function allowedSystemCheckExecutionContexts(): array
    {
        return \Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext::readiness();
    }
```

##### Health Checks

Health checks are intended to be run by monitoring systems to determine if a system is healthy and functioning correctly. Those checks should typically check the health of the system, such as database connectivity, cache availability, and other critical components.

A requirement to a typical health-check is that it should be fast, inexpensive, and not block the system.

Those system checks would have:

```php
    protected function allowedSystemCheckExecutionContexts(): array
    {
        return \Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext::cases();
    }
```

##### Long Running Checks

This type of check is essentially a health check that can take a long time to run. This type of check should be run sparingly and is only allowed to run when on CLI.
An example of such test would be a check to verify that there are no issues in log files.

Those system checks would have:

```php
    protected function allowedSystemCheckExecutionContexts(): array
    {
        return \Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext::longRunning();
    }
```

##### Other

This type would be any custom check where it needs to be run in a different context other than the templates given above. This could be anything, based on the requirements of the check.

```php
    protected function allowedSystemCheckExecutionContexts(): array
    {
        # list of contexts
        return [SystemCheckExecutionContext::CRON, SystemCheckExecutionContext::WEB];
    }
```

## Consequences

by implementing system health checks, we ensure that the system is monitored and that issues are detected early. This will help to prevent system failures and improve the overall stability of the system.
Moreover, it helps detecting issues at the time of deployment and helps to prevent issues from reaching the end-users.
