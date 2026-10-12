---
title: Authorize webhooks against their owner
date: 2026-09-29
area: framework
tags: [webhook, security, acl, app-system, admin-api]
---

## Context

Webhooks deliver event payloads to external URLs. There are two kinds:

* **App webhooks** are declared in an app manifest and written by the app lifecycle. `app_id` is set.
* **App-less webhooks** are created through the Admin API (`POST /api/webhook`). `app_id` is `NULL`.

Only app webhooks were checked against ACL privileges, using the app's role. A webhook without an app was not checked, so it received the full payload of every event it subscribed to. A caller with only `webhook:create` could receive data it is not allowed to read.

Writes to webhooks were not restricted either:

* A caller with `webhook:update` could repoint the URL of an app webhook. The webhook kept delivering under the app's role, now to the caller's endpoint.
* A caller could change or delete an app-less webhook created by somebody else, and redirect that user's data to itself.
* `POST /api/webhook` accepted any event name, including events that are not hookable or that the creator may not read. Such a webhook was stored as active but never delivered anything.

A webhook should also be able to receive less than its creator may read. An admin who creates a webhook for a shipping provider should be able to limit it to what the provider needs.

## Decision

### Every webhook has an owner

The owner of a webhook is a user or an integration:

* **App-less webhook:** the user or integration that created it. It is recorded from the Admin API session on insert, into `owner_user_id` or `owner_integration_id`. Only one is set: the integration when the request carries one, otherwise the user.
* **App webhook:** the app's integration, looked up from `app.integration_id` when the webhooks are loaded.

The owner fields cannot be written or read through the API, so the owner cannot be spoofed.

The loader skips any app-less row without an owner, which can only come from a direct database write. Both owner columns cascade on delete: removing a user or integration removes the app-less webhooks it owns.

### Existing webhooks are assigned to the oldest admin

There is no record of who created the existing app-less webhooks. The migration assigns them to the oldest admin user, so they keep delivering as before. Merchants should check them and recreate any that should run with fewer privileges.

### Delivery is authorized against the owner

When a webhook is loaded, its owner is resolved to an owner type and a list of ACL role ids:

* **`Admin`:** the owning user or integration has the admin flag and holds every privilege.
* **`Restricted`:** every other owner, with its roles from `acl_user_role`, `integration_role`, and for an app integration the app's role.

At delivery, a webhook only receives events its owner may read. `PrivilegePolicy` checks this: an `Admin` owner may read every event, and a `Restricted` owner only the events its roles allow. App webhooks and app-less webhooks are checked the same way.

### Writes are restricted by ownership

For Admin API callers:

* Updating or deleting an app webhook is rejected with `FRAMEWORK__APP_WEBHOOK_NOT_MODIFIABLE`, also for administrators. The manifest owns app webhooks.
* Updating or deleting an app-less webhook is rejected with `FRAMEWORK__WEBHOOK_NOT_OWNED`, unless the caller is its owner or an administrator.

### Subscriptions are validated on write

`SubscriptionValidator` decides whether a subscriber may subscribe to an event. The event must be hookable, every policy covering it must permit the subscriber, and the subscriber must hold the privileges the event requires. Both app and Admin API subscriptions are validated with it.

When a webhook is created or changed through the Admin API, the write is rejected if its owner isn't allowed to receive the event.

### A webhook can be limited to some of its owner's roles

An app-less webhook can be limited to some ACL roles with an optional `aclRoleIds` list. An admin owner can list any roles. A restricted owner can only list roles it holds. Without the list, the webhook uses all of its owner's roles.

### Public API

* The `webhook` entity has a new optional field `aclRoleIds`, a list of ACL role ids.
* New error codes, all returned with HTTP 400:
  * `FRAMEWORK__WEBHOOK_OWNER_MISSING`: plugin or CLI code inserts an app-less webhook without setting an owner.
  * `FRAMEWORK__APP_WEBHOOK_NOT_MODIFIABLE`: an Admin API caller tries to change or delete an app's webhook.
  * `FRAMEWORK__WEBHOOK_NOT_OWNED`: a caller who is neither the owner nor an admin tries to change or delete an app-less webhook.
  * `FRAMEWORK__WEBHOOK_EVENT_NOT_PERMITTED`: the event can't be subscribed to, because it isn't hookable or a policy refuses it.
  * `FRAMEWORK__WEBHOOK_EVENT_PRIVILEGES_MISSING`: the owner isn't allowed to receive the event. The message names the missing privileges.
* For a webhook without an app, `Hookable::isAllowed()` receives `Hookable::NO_APP_ID` as the app id.

## Considered Approaches

### Store the owner as a type and an id

An `owner_type` and an `owner_id` column cannot have a foreign key, so deleting a user or integration would not remove its webhooks.

### Narrow a webhook with a list of privileges

A list of privilege strings would allow finer narrowing, but it needs its own validation (empty, unknown, exceeding the owner) and a second place that resolves the owner's privileges. Roles are the unit merchants already manage, and the owner's roles are already resolved when a webhook is loaded.

### Require the creator to choose a role on creation

Rejected as a breaking change for existing API clients. The optional `aclRoleIds` list keeps inheriting the owner's roles as the default.

## Consequences

### Positive

* An app-less webhook no longer receives data its owner may not read.
* App webhooks can no longer be changed through the API, and app-less webhooks only by their owner or an administrator.
* A webhook on an event that requires a privilege its owner does not hold is rejected on write instead of never delivering.
* App and app-less webhooks go through one authorization path.

### Negative / trade-offs

* Existing app-less webhooks run with admin privileges until a merchant recreates them.
* Deleting a user or integration deletes the app-less webhooks it owns.
* Narrowing with `aclRoleIds` is coarser than a privilege list: limiting a webhook to a single privilege needs a role that grants exactly that privilege.
