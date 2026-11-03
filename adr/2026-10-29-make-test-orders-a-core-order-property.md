---
title: Make test orders a core order property
date: 2026-10-29
area: checkout
tags: [order, checkout, http-cache, storefront, store-api, sales-channel]
---

## Context

Merchants want to run through their checkout without producing real orders.
Self-hosted shops have no way to do that today.
Every test run reduces stock, shows up in the dashboard and, once an invoice is generated, burns a real invoice number.

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

The core already has the pieces a cleaner version needs: states on the sales channel context and its persisted payload, the shared JWT configuration, and Flow Builder rules on orders.

## Decision

A test order is a property of the order, not of the request.
`order.isTest` becomes a persisted boolean, API aware, written by the order converter in the same write as the order.
No custom field, no second write.

The converter does not look at the request.
It reads a `test-mode` state on the sales channel context.
Cart, order, payment and templates read the same state.
There are two ways to get it:

* **Test session in a live shop.** An admin action issues a short-lived signed token and a link per sales channel domain.
  A storefront route verifies the token, stores the test flag with the visitor's sales channel context, the same place a customer login lives, and redirects home.
  The flag ends with the session: browser closed, session expired, customer logout, or "end test mode" in the bar.
  No iframe, no third-party cookie, no cookie of its own, works on custom domains.
  A Store API route takes the same token and flags the context token, so headless frontends get the same behaviour.
* **Admin order creation.** The create order screen gets a "test order" toggle that the proxy order route turns into the state.
  No route name sniffing.

Both need a dedicated ACL permission.
The context factory turns the persisted flag into the state once per request.

The HTTP cache never sees the test session.
Nothing about it is part of the cache key or the cache cookie, because cached pages do not depend on it.
Checkout, account and finish are not cached anyway.
The "test mode" bar on cached pages is rendered client-side from a plain, session-scoped marker cookie that the exchange route sets next to the session.

What a test order changes, all keyed on `order.isTest`:

* **Order number.** A normal number from the regular range.
  The flag, the badge and the admin marking are the only distinction.
* **Documents.** Allowed, because merchants need to test them.
  The number is derived from the order number, `TEST-{orderNumber}`, with a suffix when the order gets a second document of the same type.
  Document number ranges stay untouched and the unique index on number and type still holds.
  Core prints no other marker; the number is the hint.
* **Stock.** Stock and sales counters are not touched.
* **Payment.** Core only exposes the flag.
  Plugin handlers reach it through the transaction's order, apps through the order in the payment payload.
  Each provider decides what sandbox means for it.
  The PayPal plugin uses its configured sandbox credentials.
* **Flows and mails.** A new rule "order is a test order".
  Default flows stay as they are, so test mails go out and merchants can test them.
  Mail templates get the flag and print a test marker.
* **Admin.** A badge in the order list, shown by default, with a filter to hide them.
  A banner in the detail, the dashboard statistics exclude test orders, and sales channels get an "open storefront in test mode" button.
* **Analytics and GMV.** Usage data collection, Shopware Analytics and Commercial's licensing turnover report, the GMV collection, skip test orders.
  The GMV collectors filter on the column instead of the SaaS custom field they check today.

Deferred to later iterations: a sales channel setting that marks every order in the channel as a test order and lets a test session pass maintenance mode, a cleanup action that deletes all test orders before go-live, and the Rufus switch-over.

## Consequences

* Downstream code has one source of truth and gets it at the same time as the order.
  Flow listeners no longer race a second write, apps and webhooks get the flag with the order entity for free.
* A new column and a migration, a new ACL permission, a storefront route and a Store API route for the exchange, and a new key in the persisted sales channel context.
  Commercial needs a companion change if a converter or persister constructor changes.
* The epic's acceptance criteria change in one point: test orders consume normal order numbers, only document numbers stay out of the productive ranges.
* A test session on a shared browser marks orders as test orders until the session ends.
  The visible bar and "end test mode" limit that.
* Test document numbers cannot collide with real ones unless a merchant uses a `TEST-` pattern themselves.
  No store extension uses that prefix for number ranges or documents.
  The generator refuses an existing number, so a collision fails loudly instead of overwriting.
* Rufus drops the detector, the persister decorator, the test number range and the cookie iframe.
  Its construction mode stays SaaS-specific until the sales channel setting lands.
  It keeps the revenue share exclusion and the BI field, both reading `order.isTest`, and backfills the custom field into the column.
  The PayPal sandbox override stays in Rufus until the plugin reads the flag.

## Example

The exchange route stores the flag with the visitor's context:

```php
#[Route(path: '/test-session/{token}', name: 'frontend.test-session.start', methods: ['GET'])]
public function start(string $token, SalesChannelContext $context): RedirectResponse
{
    $this->tokens->verify($token);
    $this->contextPersister->save(
        $context->getToken(),
        [SalesChannelContextService::TEST_MODE => true],
        $context->getSalesChannelId()
    );

    return $this->redirectToRoute('frontend.home.page');
}
```

The context factory turns the persisted flag into the state, once per request:

```php
if ($options[SalesChannelContextService::TEST_MODE] ?? false) {
    $context->addState(CheckoutTestMode::STATE);
}
```

The converter writes the flag:

```php
$data['isTest'] = $context->hasState(CheckoutTestMode::STATE);
```

Document numbers for test orders never touch a number range:

```php
if ($order->isTest()) {
    $number = sprintf('TEST-%s%s', $order->getOrderNumber(), $suffix);
} else {
    $number = $this->numberRangeValueGenerator->getValue('document_' . $type, $context, $salesChannelId);
}
```
