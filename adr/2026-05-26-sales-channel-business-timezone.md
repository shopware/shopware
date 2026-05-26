---
title: Sales-channel business timezone
date: 2026-05-26
area: after-sales
tags: [core, documents, mail, sales-channel]
---

## Context

Shopware normalizes persisted date/time values to UTC, while different parts of
the system choose their display timezone differently. The Storefront can set
Twig's default timezone from the browser timezone cookie, and the Administration
uses the logged-in user's profile timezone for date formatting. Server-side
rendering for documents and mails does not have one consistent
merchant-controlled timezone.
Depending on the entry point, templates can fall back to UTC, as reported for
documents in [#15139](https://github.com/shopware/shopware/issues/15139).

## Decision

We add an optional `businessTimeZone` field to sales channels. When it is set,
Shopware treats it as the merchant-controlled timezone for server-side rendering
of sales-channel output such as documents and mails.

Code that needs to apply a timezone for one Twig render call uses a new
`TwigTimezoneScope`, which temporarily changes Twig's default timezone and
restores the previous value afterwards:

```php
return TwigTimezoneScope::run(
    $this->twig,
    $salesChannelContext->getSalesChannel()->getBusinessTimeZone(),
    fn (): string => $this->twig->render($view, $parameters),
);
```

For 6.7, `businessTimeZone` stays nullable. If it is `NULL`, Shopware keeps the
existing render behaviour. Existing sales channels and templates are not
changed.

In the next major, this nullable compatibility behaviour will be removed. Every
sales channel will have a business timezone, with missing values migrated to
`UTC`.
`TwigTimezoneScope` remains the mechanism for applying the timezone to one Twig
render call.

## Alternatives considered

We considered basing server-side rendering on the customer's timezone instead,
for example by storing the browser timezone on the order or by adding a customer
profile setting.

We chose an optional sales-channel business timezone because it is
merchant-controlled, deterministic across render entry points, and can be
introduced as an opt-in change in 6.7 without changing existing behaviour.

## Consequences

- Merchants can set one deterministic business timezone per sales channel for
  server-rendered output.
- The change is opt-in for 6.7. Existing data, templates, and extension points
  are not required to change while no business timezone is set.
