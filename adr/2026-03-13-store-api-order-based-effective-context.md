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
5. Convert the order into a cart.
6. Store the result in dedicated request attributes as the effective request state.

This resolver must not replace the existing canonical request attributes.
It decides whether an effective object should exist for the current request.

### Original and effective request attributes

The existing request attributes remain the source of truth for the original request/session state:

- `PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT`
- `PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT`

The order-aware resolver stores dedicated effective attributes, for example:

- an effective `Context`
- an effective `SalesChannelContext`
- an effective `Cart`

The exact constant names can be introduced with the implementation.
The important part is the semantic split:

- canonical attributes describe the actual incoming request state
- effective attributes describe the synthetic state used to evaluate the current route

### Value resolvers inject the effective request state if it exists

Argument value resolvers do not decide whether an order-based state should be created.
They only check whether an effective object was added by the dedicated resolver and inject it if present.
Otherwise they fall back to the canonical request attributes and keep the current behavior.

This applies in particular to:

- `SalesChannelContextValueResolver`
- `ContextValueResolver`
- `CartValueResolver`
- `CriteriaValueResolver`

This creates a clear split of responsibilities:

- the order-aware resolver decides whether an effective state exists
- the value resolvers optionally inject that effective state and otherwise fall back

This ensures that opted-in route handlers receive the order-based `SalesChannelContext`, `Context` and `Cart` without changing the route signatures.

For routes that do not opt in, or requests that do not provide an `orderId`, behavior remains unchanged.

### Restored objects are request-local

Restored carts and restored sales channel contexts are evaluation objects only.
They must not be treated like the persisted request/session state.

In particular, the implementation must not persist these restored objects into database or Redis-backed storage.
This applies to both context persistence and cart persistence.

They exist only for the lifetime of the current request in order to evaluate availability against an existing order.

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
