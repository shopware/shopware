---
title: Dispatch DAL Write Events in System Scope
date: 2026-06-23
area: framework
tags: [dal, acl, api, events, extensions]
status: accepted
---

## Context

Shopware extensions can add DAL entities with their own ACL privileges and can react to core entity events, for example `product.written`.
Those listeners often write extension-owned technical data derived from the core write.

Today these follow-up writes happen in the same CRUD API scope as the original API request.
That makes API permissions depend on active extensions: an API client updating a product may also need `custom_extension_entity:create` or `custom_extension_entity:update`, even when the API payload only writes `product`.

The intended extension best practice is that technical listener side effects use system scope themselves.
That is not enough as a platform behavior because existing extensions do not always do this, and their listeners can break otherwise valid API clients.

## Decision

DAL post-write event dispatch runs in `Context::SYSTEM_SCOPE`, while preserving the same `Context` instance and source.
The context source must not be replaced with `SystemSource`.
While dispatching these events, the context is marked with `Context::SYSTEM_SCOPE_DAL_WRITE_EVENT`.
The marker identifies system scope that was added by DAL post-write dispatch, not by explicit application code.

Conceptually:

```php
$context->scope(
    Context::SYSTEM_SCOPE,
    fn () => $eventDispatcher->dispatch($event),
    [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT],
);
```

API ACL validation remains attached to the original write commands and payload.
Listeners that use the event context for follow-up DAL writes execute those writes as technical side effects, so the API caller does not need extension-internal entity privileges.

The original caller identity stays available through the preserved context source.
Listeners that intentionally need a user permission decision can still check the source or call `Context::isAllowed()` explicitly.
Subsystems that use system scope for non-ACL behavior can use `Context::SYSTEM_SCOPE_DAL_WRITE_EVENT` to distinguish implicit DAL write-event system scope from an explicit system-scope opt-in.
For example, private media visibility restrictions still apply during implicit DAL write-event dispatch.
If a listener deliberately needs private media access, it can still wrap that specific read in `$context->scope(Context::SYSTEM_SCOPE, ...)`; re-entering system scope suppresses the DAL write-event marker for that callback.

## Affected Events

This applies to DAL post-write events only:

- `EntityWrittenContainerEvent`
- nested `EntityWrittenEvent`, dispatched as `<entity>.written`
- nested `EntityDeletedEvent`, dispatched as `<entity>.deleted`

The rule applies to every DAL entity, including extension and custom entities.
There must be no entity-name allowlist, because extension entities are dynamic and the bug is caused by active extensions changing the side effects of otherwise valid API writes.

The affected dispatch sites are repository write results, Sync API write results, and version create or merge write results.
These sites dispatch through `Context::scope(Context::SYSTEM_SCOPE, ..., [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT])`, so system-scope dispatch and marker cleanup are handled consistently in one place.
The container event is enough as the implementation boundary because nested DAL write events are dispatched from the container event; dispatching the container in system scope makes subscribers to both `EntityWrittenContainerEvent` and `<entity>.written` / `<entity>.deleted` observe the same scoped context.

These events must stay in the original caller scope:

- `EntityWriteEvent`
- `EntityDeleteEvent`
- `PreWriteValidationEvent`
- `PostWriteValidationEvent`
- `WriteCommandExceptionEvent`
- `BeforeVersionMergeEvent`
- DAL read/search/load/aggregation events
- normal business, checkout, and Flow events

The reason is that those events are validation, command mutation, failure reporting, read, or business-process hooks.
Most importantly, API ACL validation is attached to the write commands before persistence.
Moving pre-write validation events to system scope would skip the caller's actual API ACL check instead of only making post-write technical side effects predictable.

## Consequences

- API write permissions become predictable again: they are based on the API route, payload, and DAL commands, not on active listener side effects.
- Existing extensions that write extension-owned data from write listeners are fixed without opting in.
- Audit and source-sensitive behavior can still see the original user, integration, app, or sales-channel source.
- Listener writes using the event context bypass DAL write ACL because the scope is system.
- Nested writes triggered by listeners inherit system scope while dispatch is running.
- Listeners that branch on `Context::getScope()` may observe `system` instead of `crud` during DAL write events.
- Private media visibility is not implicitly widened by this change; extensions still need an explicit system-scope read when they intentionally access private media.

## Rejected Alternatives

- **Use `SystemSource` for event dispatch:** also skips ACL, but loses the caller identity that listeners and audit fields may need.
- **Only document the best practice:** keeps the model clean, but does not fix existing extensions that already break API clients.
- **Require plugins to declare listener permissions:** makes permission impact visible, but keeps API permissions coupled to plugin internals.
- **Expand roles when plugins are installed:** avoids some runtime failures, but grants extension entity access outside the specific side effect.
- **Use a custom "skip listener ACL" flag instead of system scope:** is narrower in name, but would introduce a second ACL bypass path for write validation. The accepted state is only a marker for subsystems that must distinguish implicit DAL write-event system scope from explicit system scope.
- **Move listener side effects to async jobs:** is useful for indexing and denormalized data, but not a general fix for synchronous invariants.
