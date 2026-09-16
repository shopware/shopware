---
title: Order-based effective Store API context
date: 2026-03-13
area: checkout
tags: [checkout, order, context, store-api, routing, sales-channel]
---

# ADR: Order-based effective Store API context

## Context

Several Store API routes derive their result exclusively from the current `SalesChannelContext`.
This is correct for regular storefront and headless requests, but it is insufficient for "after order" use cases.

The storefront already solves this for the account order edit page.
It converts the order back into a cart and reassembles a matching `SalesChannelContext` before calling the `CheckoutGatewayRoute`.

In a headless setup there is no equivalent mechanism.
Routes such as `PaymentMethodRoute` and `CheckoutGatewayRoute` can only operate on the current request context.
Passing the current context token is not sufficient when the availability must be evaluated against the data of an existing order.

We do not want to solve this in every route separately.
We also do not want to replace the canonical request context attributes with the restored order-based context.
The original request context is still the actual session context and must remain available as-is.

## Decision

We will introduce the concept of an effective request state for Store API routes that need to evaluate availability for an **existing order**.

### Opt-in routes

Store API routes that support order-based evaluation will opt in explicitly via the `defaults` of their Symfony `Route` attributes.
This keeps the behavior local to the affected routes and avoids implicit magic for all Store API requests.

The route default indicates that the route may resolve an order-based effective request state from an `orderId`.
One possible default name is `_allowOrderRestoration`.

### Dedicated order-aware request resolver

A dedicated resolver/listener will run after the normal sales channel request context resolution.
Its responsibility is limited to the following steps:

1. Check whether the current route opted in.
2. Check whether an `orderId` is present in the request.
3. Load and validate the order for the current caller.
4. Reassemble an order-based `SalesChannelContext`.
5. Convert the order into a cart and process it.
6. Store the result in dedicated request attributes as the effective request state.

This resolver must not replace the existing canonical request attributes.
It decides whether an effective object should exist for the current request.

### Original and effective request attributes

The existing request attributes (`PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT`, `PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT`) remain the source of truth for the original request and session state.
The order-aware resolver stores the effective `Context`, `SalesChannelContext` and `Cart` under dedicated attributes, whose names come with the implementation.

The semantic split is what matters: canonical attributes describe the actual incoming request, effective attributes describe the synthetic state used to evaluate the current route.

### Value resolvers inject the effective request state if it exists

Argument value resolvers do not decide whether an order-based state should be created.
They check whether an effective object was added by the dedicated resolver, inject it if present, and otherwise fall back to the canonical attributes.
This applies to the value resolvers for `SalesChannelContext`, `Context`, `Cart` and `Criteria`.

Opted-in route handlers therefore receive the order-based objects without any change to their signatures.
For routes that do not opt in, or requests without an `orderId`, behavior remains unchanged.

### The restored cart is processed before the route sees it

The restored cart runs through the regular cart processing before it is handed to the route.
An unprocessed cart is not enriched and matches no rules, so any availability derived from it would simply be wrong.

Several places in the platform restore a cart today and each one processes it differently, from not at all up to a full recalculation.
Opted-in Store API routes settle on one behavior: process the cart and match rules against the current state of the shop.

Availability is answered for today, not for the day the order was placed.
These routes answer whether an existing order can still be paid or shipped with a given method now, so currently matching rules are the correct basis, and the rule IDs stored on the order do not restrict that evaluation.

### Permissions keep the restored objects request-local

Restored carts and restored sales channel contexts are evaluation objects only.
They must not be persisted into database or Redis-backed storage, neither as a context nor as a cart, and exist only for the lifetime of the current request.

This does not come for free: processing a cart is exactly what can trigger cart persistence, so the guarantee is carried by the permissions the restored context runs with.
The resolver therefore names the permission set it grants explicitly instead of inheriting the defaults used for admin order editing.

Skipping cart persistence stays part of that set, since it is what keeps the restored cart out of storage.
Pinning prices and relaxing stock and product availability checks stay as well, a customer must not be blocked from paying an existing order because a product has sold out since.

Because that set also grants permissions that would be unsafe for writes, opt-in is limited to routes that only read.
A route that mutates data must not opt in.

Restored carts and contexts are already identifiable, both carry the `OrderConverter::ORIGINAL_ID` extension.
That marker, not the permission, is the anchor for any hard guard against persistence, because a permission can be overridden by third-party code listening on the cart persist event.

### Failure handling

If a route opted in and an `orderId` is present but the effective state cannot be built, the request fails.
The resolver must never fall back to the session context silently, because the client would then receive availability for the current session while believing it asked about the order.

An order that does not exist and an order that does not belong to the caller produce the same response, so that the existence of an order stays unobservable.
An order the caller may see but whose restoration fails produces its own error.

Cart errors produced by processing are not failures in this sense.
They are part of the result and are returned in the response payload as usual.

### The request token remains unchanged

The incoming context token remains the token of the original request.
The reassembled order-based `SalesChannelContext` may use an internal token for evaluation, but that token is not treated as the client's canonical Store API token and must not become a persisted public session token.

### Shared restoration logic

The order-based context must not be rebuilt independently in every route.
The shared implementation should reuse the existing order reassembly logic in `OrderConverter::assembleSalesChannelContext()`.

## Consequences

Headless clients gain the same order-based availability checks that already exist in the storefront for after-order payment changes.

Store API routes remain focused on their business behavior instead of manually rebuilding order-specific contexts.

The original request context stays available in the request attributes for infrastructure code and debugging.
This reduces the risk of treating a synthetic evaluation context as if it were the persisted session context.

The solution adds some plumbing in the request resolution and value resolver layers, but keeps the distinction between original and restored state explicit.

Routes that need an order-based evaluation must still define how the order is authorized for the current caller.
An `orderId` alone must never be enough to expose order-derived availability information.

Opt-in stays limited to read-only routes. Extending this to order mutations would need its own decision about the permissions such a route may run with.
