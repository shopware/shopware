---
title: Make test orders a core order property
date: 2026-10-29
area: checkout
tags: [order, checkout, http-cache, storefront, store-api, sales-channel]
---

## Context

Merchants want to run through their checkout without producing real orders.
Self-hosted shops have no way to do that today.
Every test run consumes a real order number, reduces stock, shows up in the dashboard and, once an invoice is generated, burns a real invoice number.

The SaaS platform (Rufus) has had test orders for years, built around the platform's shop status:

* While the shop is not live, every order is a test order.
* In a live shop, an admin gets a one-week JWT in a cookie on the canonical domain.
  Custom domains fetch that cookie through a hidden iframe and the Storage Access API.
* A detector reads the request stack, checks the cookie and the route name, and decides per request.
* The order number comes from a separate `test_order` number range, smuggled into the converter through the recalculation extension.
* A subscriber writes the custom field `saas_test_order` in a second write after the order exists, and skips the revenue share.
* PayPal is pinned to a shared sandbox merchant for such orders.

Everything else treats test orders as real: flows, mails, documents, stock, statistics.
The admin cannot filter them, and the token is part of the HTTP cache key, so every admin login gets a private cache namespace and never warms the shared one.

The core already has the pieces a cleaner version needs: states on the sales channel context, maintenance mode with an event that can allow a client, number range types, the shared JWT configuration, and Flow Builder rules on orders.

## Decision

A test order is a property of the order, not of the request.
`order.isTest` becomes a persisted boolean, API aware, written by the order converter in the same write as the order.
No custom field, no second write.

The converter does not look at the request.
It reads a `test-mode` state on the sales channel context.
Cart, order, payment and templates read the same state.
There are three ways to get it:

* **Sales channel test mode.** A sales channel setting that marks every order placed in it as a test order.
  This replaces the "shop is not live" branch.
* **Test session in a live shop.** An admin action issues a short-lived signed token and a link per sales channel domain.
  A storefront route verifies the token, sets a first-party `HttpOnly` cookie that lives a few hours, and redirects home.
  No iframe, no third-party cookie, works on custom domains.
  The Store API accepts the same token in a header, like the context token.
* **Admin order creation.** The create order screen gets a "test order" toggle that the proxy order route turns into the state.
  No route name sniffing.

The token is verified once per request and lands in a request attribute.
The context creation listener turns the attribute, or the sales channel setting, into the state.

The HTTP cache never sees the test session.
The cookie is not part of the cache key or the cache cookie, because cached pages do not depend on it.
Checkout, account and finish are not cached anyway.
The "test mode" bar on cached pages is rendered client-side from a plain marker cookie that is set next to the real one.
In maintenance mode the test session counts as an allowed client, through the existing `MaintenanceModeRequestEvent`.
The resolver already keeps responses for allowed clients out of the cache while maintenance is on, so this replaces the SaaS construction mode for free and leaves the live cache alone.

What a test order changes, all keyed on `order.isTest`:

* **Order number.** A new number range type `order_test` with the default pattern `TEST-{n}`, selected in the converter.
* **Documents.** Allowed, because merchants need to test them.
  The number is derived from the order number, `TEST-{orderNumber}`, with a suffix when the order gets a second document of the same type.
  Document number ranges stay untouched and the unique index on number and type still holds.
  The document config carries the flag so templates print a watermark.
* **Stock.** Stock and sales counters are not touched.
* **Payment.** Core only exposes the flag.
  Plugin handlers reach it through the transaction's order, apps through the order in the payment payload.
  Each provider decides what sandbox means for it.
  The PayPal plugin uses its configured sandbox credentials.
* **Flows and mails.** A new rule "order is a test order".
  Default flows stay as they are, so test mails go out and merchants can test them.
  Mail templates get the flag and print a test marker.
* **Admin.** Badge and filter in the order list, a banner in the detail, the dashboard statistics exclude test orders, and sales channels get an "open storefront in test mode" button.
* **Analytics.** Usage data collection skips test orders.

Deferred to a follow-up: a cleanup action that deletes all test orders before go-live, and the Rufus switch-over.

## Consequences

* Downstream code has one source of truth and gets it at the same time as the order.
  Flow listeners no longer race a second write, apps and webhooks get the flag with the order entity for free.
* A new column and a migration, a new number range type, a new sales channel setting, and a new store-api header.
  The converter decides the number range type, so Commercial needs a companion change if a constructor changes.
* A test cookie on a shared browser marks orders as test orders until it expires.
  The short TTL and the visible bar limit that.
  The token cannot be revoked before it expires, which is why it lives hours, not a week.
* Test document numbers cannot collide with real ones unless a merchant uses a `TEST-` pattern themselves.
* Rufus drops the detector, the persister decorator, the number range migration and the cookie iframe.
  It keeps the revenue share exclusion and the BI field, both reading `order.isTest`, and backfills the custom field into the column.
  The PayPal sandbox override stays in Rufus until the plugin reads the flag.

## Example

Turning the request into the state, once per request:

```php
public function __invoke(SalesChannelContextCreatedEvent $event): void
{
    $request = $this->requestStack->getMainRequest();

    if ($request?->attributes->getBoolean(PlatformRequest::ATTRIBUTE_TEST_SESSION)
        || $event->getSalesChannelContext()->getSalesChannel()->isTestMode()) {
        $event->getSalesChannelContext()->addState(CheckoutTestMode::STATE);
    }
}
```

The converter writes the flag and picks the number range:

```php
$isTest = $context->hasState(CheckoutTestMode::STATE);

$data['isTest'] = $isTest;
$data['orderNumber'] = $this->numberRangeValueGenerator->getValue(
    $isTest ? 'order_test' : OrderDefinition::ENTITY_NAME,
    $context->getContext(),
    $context->getSalesChannelId()
);
```

Passing maintenance mode with a test session:

```php
public function __invoke(MaintenanceModeRequestEvent $event): void
{
    if ($event->getRequest()->attributes->getBoolean(PlatformRequest::ATTRIBUTE_TEST_SESSION)) {
        $event->allowClient();
    }
}
```

Document numbers for test orders never touch a number range:

```php
if ($order->isTest()) {
    $number = sprintf('TEST-%s%s', $order->getOrderNumber(), $suffix);
} else {
    $number = $this->numberRangeValueGenerator->getValue('document_' . $type, $context, $salesChannelId);
}
```
