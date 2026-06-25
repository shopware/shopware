---
title: DAL write event listeners no longer expand API ACL requirements
issue: #17697
---
# API
* Changed DAL post-write event dispatching after Admin API and Sync API writes to use system scope while preserving the original context source.

___
# Upgrade Information
## DAL write event listeners run in system scope
DAL post-write events such as `EntityWrittenContainerEvent` and entity-specific `.written` events are now dispatched in system scope after Admin API and Sync API writes, while preserving the original context source.

This matters for plugins that subscribe to core write events and update their own entities as a side effect. Previously, a listener on an event such as `product.written` still ran in the CRUD context of the triggering API request. When that listener wrote an extension-owned entity, the API user also needed permissions for that extension entity, even though the submitted request only changed products.

With this change, listener-side DAL writes are treated as trusted system-side follow-up work of the original write. API consumers only need the privileges required for the submitted write payload; plugin-internal denormalization, synchronization, indexing, or bookkeeping writes performed from DAL write listeners no longer expand the caller's ACL requirements.

Extension authors can still inspect who triggered the write via `$event->getContext()->getSource()`. If a listener intentionally wants to make a side effect depend on the triggering user or integration, it should check the source explicitly instead of relying on `$event->getContext()->getScope()` being `Context::USER_SCOPE`.

Private media visibility is not implicitly widened by this change. During DAL write-event dispatch, Shopware marks the context with `Context::SYSTEM_SCOPE_DAL_WRITE_EVENT` so private media searches still apply normal visibility restrictions. If a listener intentionally needs private media access, wrap that specific read in `$context->scope(Context::SYSTEM_SCOPE, ...)`; explicit system-scope reads continue to opt in to private media visibility.
