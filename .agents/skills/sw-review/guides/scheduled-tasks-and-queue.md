---
guide: scheduled-tasks-and-queue
title: Background work stays in the background
personas: [architecture, maintainer]
rules:
  - { id: QUEUE-001, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#environment", fixture: "tests/guide/scheduled-tasks-and-queue/ignore" }
  - { id: QUEUE-002, since: 2026-10-09, source: "coding-guidelines/core/unit-tests.md#focus-on-behavior-not-implementation-effective-unit-testing-principles", fixture: "" }
  - { id: QUEUE-003, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#queue-work", fixture: "" }
  - { id: QUEUE-004, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#configuration-and-operations", fixture: "" }
---

## Why this guide exists

The message queue worker and the scheduled task runner are requirements of running Shopware, not optional extras. Work that belongs to them must not leak into commands or requests, and a task must be tested for what it does, because nobody watches a task that silently does nothing. Moving theme cleanup from `DeleteThemeFilesTaskHandler` into `theme:compile` broke cached storefronts, and a product stream task once ran every day without any effect until someone noticed.

## Check

- **QUEUE-001** Look for a command, controller, subscriber or compile step that calls the service behind a scheduled task or message handler directly, or a branch that runs the work inline "if the worker is down". The work then runs in every shop at the wrong moment and without the task's locking and retry. Operators who need it at once already have `scheduled-task:run-single <name>` and `scheduled-task:schedule --immediately`. Rule: [platform-scope.md#environment](../../../../coding-guidelines/core/platform-scope.md#environment). Example: `ThemeCompileCommand.php` called `UnusedThemeDirectoryDeleter` directly because the task did not run in some environments; theme folders were deleted while cached pages still used them.
- **QUEUE-002** Look at the tests of a new or changed handler: they must assert the effect the task is named for (rows deleted, mapping rebuilt, messages dispatched), not only that `run()` finished. A task without effect still reports `scheduled` with a fresh `lastExecutionTime`, so merchants see stale data and nobody notices. Rule: [unit-tests.md#focus-on-behavior-not-implementation-effective-unit-testing-principles](../../../../coding-guidelines/core/unit-tests.md#focus-on-behavior-not-implementation-effective-unit-testing-principles). Example: `UpdateProductStreamMappingTaskHandler.php` ran every day, but `ProductStreamUpdater` skipped re-indexing, so the product stream mapping never changed.
- **QUEUE-003** Look for expensive work that a write or an app lifecycle event triggers inside the subscriber: re-indexing, synchronisation, bulk updates. It runs inside the merchant's save request, slows down every write and can time out on large catalogues. Dispatch a message instead, and only when a relevant value actually changed. Rule: [platform-scope.md#queue-work](../../../../coding-guidelines/core/platform-scope.md#queue-work). Example: `SeoUrlTemplateChangeSubscriber.php` re-indexed SEO URLs synchronously, even for an identical update; `AppStaticSeoUrlSynchronizer.php` did heavy work on every domain write and app update.
- **QUEUE-004** Look for a new scheduled task without a RELEASE_INFO entry that names its interval and how to switch it off. Operators see new load and new rows in `scheduled_task` without knowing where they come from; an `@experimental` task is no exception. Rule: [Configuration and operations](../../../../coding-guidelines/core/backward-compatibility.md#configuration-and-operations). Example: the hourly `McpToolResultCacheCleanupTask` shipped without an entry, so operators saw a new task without knowing where it came from.

## Do not flag

CI covers:

- `MessageHandlerFinalRule` (PHPStan): classes with `#[AsMessageHandler]` must be final.
- `NoDelayStampRule` (PHPStan): `new DelayStamp` is rejected because not every messenger transport supports it.
- `MessagesShouldNotUsePHPStanTypes` (PHPStan): async and low-priority messages must not use `@phpstan-type` annotations.

Legitimate patterns:

- A handler that throws, or throws `RecoverableMessageHandlingException`, so the transport's retry strategy (exponential backoff in `framework.yaml`) retries the message.
- `shouldRescheduleOnFailure(): true` on cleanup tasks, and `shouldRun()` reading the parameter bag for config-driven tasks.
- A new task registered with the `shopware.scheduled.task` tag and a final handler extending `ScheduledTaskHandler`.
- A command that dispatches the task's message instead of doing the work.
- Interval choices: `DAILY` or `HOURLY` are product decisions, not review findings.
- Wording and placement of the QUEUE-004 entry: the release-docs guide owns them.

## Severity

- `blocking`, category `correctness`: moving the work inline breaks correctly configured shops, as the theme cleanup in `theme:compile` did through the HTTP cache. Pair it with the CACHE- or SCOPE- finding.
- `major`, category `scope`, `requires_human: true`: work moved into a command or request path, or an inline fallback, without an immediate outage (QUEUE-001).
- `major`, category `tests`: the handler's logic changed and no test asserts its effect (QUEUE-002).
- `major`, category `performance`: heavy work runs synchronously in a subscriber on every write (QUEUE-003).
- `minor`, category `tests`: a new handler whose test checks only that collaborators were called.
- `minor`, category `docs`: a new scheduled task without a RELEASE_INFO entry (QUEUE-004).

## Retired

(none yet)
