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

The route default `_allowOrderRestoration` (`PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION`) indicates that the route may resolve an order-based effective request state from an `orderId`.
`/store-api/payment-method`, `/store-api/shipping-method` and `/store-api/checkout/gateway` opt in.

The `orderId` is read from the request the usual way, path attribute first, then query, then body.
No dedicated header is introduced, headers are not part of the HTTP cache key.
A route that wants an explicit `/{orderId}` URL can declare the placeholder, the resolver picks it up from the attributes like any other source.

Responses built on an effective state are personal to the caller and the order.
The resolver marks them as not cacheable, regardless of where the `orderId` came from.

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

The existing request attributes (`PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT`, `PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT`) remain the source of truth for the original request and session state.
The order-aware resolver stores the effective `SalesChannelContext`, `Context` and `Cart` under the dedicated attributes `sw-effective-sales-channel-context`, `sw-effective-context` and `sw-effective-cart` (`PlatformRequest::ATTRIBUTE_EFFECTIVE_*`).

The semantic split is what matters: canonical attributes describe the actual incoming request, effective attributes describe the synthetic state used to evaluate the current route.

This mirrors the storefront, where the order context is built next to the session context and used for a single call, while the page itself keeps running on the session context.

The canonical attributes cannot simply be replaced, because the restored context carries its own token.
Everything that reads the attributes, the context token on the response, the cache hash, subscribers working with the current context, would otherwise act on the synthetic state.
Route handlers still receive the order-based objects, see below, only the attributes themselves stay the session state.

### Value resolvers inject the effective request state if it exists

Argument value resolvers do not decide whether an order-based state should be created.
They check whether an effective object was added by the dedicated resolver, inject it if present, and otherwise fall back to the canonical attributes.
This applies to the value resolvers for `SalesChannelContext`, `Context`, `Cart` and `Criteria`.

Opted-in route handlers therefore receive the order-based objects without any change to their signatures.
For routes that do not opt in, or requests without an `orderId`, behavior remains unchanged.

### The restored state is not recalculated

The restored context and cart are used exactly as `OrderConverter` builds them.
The context keeps the rule IDs stored on the order and the cart is not processed, so rules are not re-evaluated against the current state of the shop.

Availability is answered on the basis the order was placed with.
This is what the Storefront edit-order page and `POST /store-api/order/payment` have always done, and a client listing the methods for an order has to get the same answer the set-payment route validates against.

Places that restore an order treat its rules differently today, from keeping the stored ones up to a full recalculation.
The opted-in routes, the edit-order page and the set-payment route converge on the converter's behavior instead of on a recalculation.
`SalesChannelContextRestorer::restoreByOrder()` is deprecated and not reused, it re-matches the rules and loads the order without checking that the caller may see it.

### Restored objects stay request-local

Restored carts and restored sales channel contexts are evaluation objects only.
They must not be persisted into database or Redis-backed storage, neither as a context nor as a cart, and exist only for the lifetime of the current request.

The restoration path itself never persists anything: assembling the context, converting the order and handing the cart to the cart service all happen in memory.
The permissions the restored context runs with are a safeguard for third-party code that recalculates the restored cart.
`OrderRestorer::PERMISSIONS` names that set explicitly instead of inheriting the defaults used for admin order editing.

`SKIP_CART_PERSISTENCE` is part of it and keeps a recalculated cart out of storage.
Pinning prices and promotions and relaxing stock and product availability checks keep it stable, a customer must not be blocked from paying an existing order because a product has sold out since.
Price overwrites, which admin order editing allows, are left out.

Because that set also grants permissions that would be unsafe for writes, opt-in is limited to routes that only read.
A route that mutates data must not opt in.

Restored carts and contexts are already identifiable, both carry the `OrderConverter::ORIGINAL_ID` extension.
That marker, not the permission, is the anchor for any hard guard against persistence, because a permission can be overridden by third-party code listening on the cart persist event.
No such guard is added to the cart persisters.
The Administration's convert-order-to-cart flow persists converted carts on purpose and those carry the same marker, so a guard keyed on it would break that flow.

### Failure handling

If a route opted in and an `orderId` is present but the effective state cannot be built, the request fails.
The resolver must never fall back to the session context silently, because the client would then receive availability for the current session while believing it asked about the order.

A malformed `orderId` fails with `CHECKOUT__INVALID_UUID`.
A request without a logged-in customer or guest fails with `CHECKOUT__ORDER_CUSTOMER_NOT_LOGGED_IN`.
An order that does not exist and an order that does not belong to the caller both produce `CHECKOUT__ORDER_ORDER_NOT_FOUND`, so that the existence of an order stays unobservable.
An order the caller may see but whose restoration fails produces `CHECKOUT__ORDER_RESTORATION_FAILED` (HTTP 400), unless the failure is already an order error.

Cart errors the route returns, like the blocked payment and shipping method errors of the checkout gateway, are not failures in this sense.
They are part of the result and are returned in the response payload as usual.

### The request token remains unchanged

The incoming context token remains the token of the original request.
The reassembled order-based `SalesChannelContext` may use an internal token for evaluation, but that token is not treated as the client's canonical Store API token and must not become a persisted public session token.

### Shared restoration logic

The order-based context must not be rebuilt independently in every route.
`OrderRestorer::restore()` builds context and cart from a loaded order through `OrderConverter::assembleSalesChannelContext()` and `OrderConverter::convertToCart()`, so extensions of the converter apply here as well.
`OrderRestorer::addRequiredAssociations()` adds what the converter needs to the criteria the order is loaded with.

The resolver does not load the order on its own, it goes through the order route with that criteria.
The order is therefore authorized exactly like on `/store-api/order`: sales channel and customer filter, `OrderCriteriaEvent` and any decoration of the route, for example order visibility for B2B employees.

## Consequences

Headless clients gain the same order-based availability checks that already exist in the storefront for after-order payment changes.

Store API routes remain focused on their business behavior instead of manually rebuilding order-specific contexts.
The Storefront edit-order page and `POST /store-api/order/payment` use `OrderRestorer` as well instead of their own copies of the restoration.

The original request context stays available in the request attributes for infrastructure code and debugging.
This reduces the risk of treating a synthetic evaluation context as if it were the persisted session context.

The solution adds some plumbing in the request resolution and value resolver layers, but keeps the distinction between original and restored state explicit.

The resolver authorizes the order through the order route, so extensions that change who may see an order apply to the opted-in routes as well.
An `orderId` alone must never be enough to expose order-derived availability information.

Opt-in stays limited to read-only routes. Extending this to order mutations would need its own decision about the permissions such a route may run with.
