---
title: Sales-channel business timezone
date: 2026-06-24
area: after-sales
tags: [core, documents, mail, sales-channel]
---

## Context

Shopware normalizes persisted date/time values to UTC, while different parts of the system choose their display timezone differently. The Storefront can set Twig's default timezone from the browser timezone cookie, and the Administration uses the logged-in user's profile timezone for date formatting. Server-side rendering for documents and mails does not have one consistent merchant-controlled timezone.

Depending on the entry point, templates can fall back to UTC, as reported for documents in [#15139](https://github.com/shopware/shopware/issues/15139).

## Decision

We add an optional `businessTimeZone` field to sales channels. When it is set, Shopware treats it as the merchant-controlled timezone for server-side rendering of sales-channel output such as documents and mails. Code that needs to apply a timezone for one Twig render call uses `renderWithTimezoneOverride` on Shopware's Twig environment, which temporarily changes Twig's default timezone and restores the previous value afterwards:

```php
return $this->twig->renderWithTimezoneOverride(
    $view,
    $parameters,
    $salesChannelContext->getSalesChannel()->getBusinessTimeZone(),
);
```

In 6.7, a `NULL` value keeps the existing render behaviour. Existing sales channels and templates are not changed.

Starting with 6.8, the `NULL` case becomes deterministic: rendering through `renderWithTimezoneOverride` falls back to Twig's configured default timezone, captured before runtime overrides such as the Storefront browser-timezone listener mutate the environment. Documents then render in the same timezone regardless of whether they are generated from a Storefront request, the Administration, or the message queue.

## Alternatives considered

We considered making the field required with a `UTC` default, backfilled by migration in the next major. We rejected this because `UTC` is exactly the behaviour reported as a bug, and the backfill would override installations that already configure a different Twig default timezone via `twig.date.timezone`.

We also considered basing server-side rendering on the customer's timezone instead, for example by storing the browser timezone on the order or by adding a customer profile setting. This remains possible as a separate, explicit feature later; it requires persisting the customer timezone and is not blocked by an optional business timezone.

## Consequences

- Merchants can set one deterministic business timezone per sales channel for server-rendered output.
- The change is opt-in for 6.7. Existing data, templates, and extension points are not required to change while no business timezone is set.
- From 6.8, rendering without a business timezone no longer depends on the entry point: the browser-timezone cookie no longer affects document rendering, and `twig.date.timezone` configuration keeps working.
