---
title: Decouple price calculation basis from tax display state
date: 2026-08-11
area: checkout
tags: [core, checkout, cart, tax, price, customer-group]
status: proposed
---

## Context

Every product price stores a gross and a net value. A single tax state (`gross` / `net` / `tax-free`,
derived from `customer_group.display_gross`) decides three things at once: which stored value is used,
how taxes are calculated, and whether the customer sees "incl. VAT" or "excl. VAT".

Because of this coupling, gross display always means "fixed gross price". The included tax depends on the
customer's country, so the merchant's net proceeds vary per country. Merchants want the opposite
combination: calculate from the fixed net price (same proceeds everywhere) while still showing gross
prices to B2C customers.

Two code facts shape the solution:

1. There is no display layer. `CalculatedPrice` carries one number, already in the right tax state.
   Templates, Store API, documents and admin print it as-is; only the VAT labels branch on the tax state.
   The display value has to come out of the calculation correct, we cannot convert it at the end.
2. The conversion we need already exists. `QuantityPriceDefinition::$isCalculated = false` tells
   `GrossPriceCalculator` "this value is net, gross it up with the tax rules first". Today almost nothing
   sets that flag.

## Decision

We add a price basis setting that decides which stored value is authoritative. The tax state keeps its
current meaning (tax math flavor + labels), so `display_gross` finally means what its name says.

- New nullable field `customer_group.price_basis`: `'net'`, `'gross'` or `NULL`.
  - `NULL` (default): everything works exactly as today, the basis follows the display mode.
  - `'net'`: the stored net value is always used. With gross display, the price definition is passed with
    `isCalculated = false`, so the gross price is derived live from net and the shipping country's tax
    rules. With net or tax-free display the net value is used as-is.
  - `'gross'`: reserved for the symmetric case (fixed gross, net display). Deferred, because
    `NetPriceCalculator` ignores `isCalculated` and needs its own rounding analysis first.
- A new decoratable `AbstractPriceSelector` service replaces the four duplicated gross-vs-net branches
  (`ProductPriceCalculator`, `DeliveryCalculator`, `CurrencyPriceCalculator`, the app-script price
  facades). It returns the selected value plus the `isCalculated` flag.
- Nothing downstream changes. Cart math, orders, documents, tax reporting, rules, recalculation, both API
  schemas and all storefront templates keep their current shape, because the derived gross enters the
  pipeline at the same point a stored gross does today.

How exact is "fixed net"? The displayed gross must be a valid cash amount, so it gets rounded, and the
merchant's realized net is the stored net plus/minus at most half a rounding unit per unit sold. That
shrinks the per-country variance from full VAT percentage points to sub-cent noise. Net exact to the cent
would need net-authoritative totals with per-surface gross derivation and visible penny mismatches
(Alternative A). We accept the sub-cent variance.

Loose ends handled:

- **Caching**: derived gross prices depend on the country's tax rates, not just the tax state. When a
  customer group sets a basis, the HTTP cache cookie and the entity cache hash additionally include a
  fingerprint of the context's tax rules.
- **Sorting and filtering** keep using the stored gross column (a per-country gross cannot be indexed).
  For linked prices that matches the derived gross at the product's home tax rate; other countries drift
  by the rate delta.
- **Admin**: the customer group detail page gets a "price basis" select plus a hint that manually
  maintained gross values are ignored in net-basis mode.

End state: `NULL` is transitional. With v6.8 a migration backfills `price_basis` from `display_gross`,
the column becomes `NOT NULL`, and the two fields are fully orthogonal from then on (UPGRADE entry;
requires the `'gross'` basis to be implemented). Until then `NULL` keeps the old coupling alive for every
writer that does not know the field: old core during blue-green, plugins, ERP syncs, API clients.

## Alternatives considered

**A) Second tax state on the context**: calculate net-authoritative, derive gross per display surface.
Exact net to the cent, but the displayed gross unit price times quantity no longer matches the charged
total (up to a cent per line), it cannot reconcile with vertical tax calculation at all, and it touches
`CalculatedPrice`, both API schemas, ~30 templates and every headless client. Not doable without a major.

**B) Display-only decoration in the product layer** (listing shows gross, cart stays net). Rejected:
listing and cart would show different prices.

**C) Global system config switch.** Cannot express mixed setups (B2C gross display + B2B net display,
both on a fixed net basis), and the display toggle already lives on the customer group.

**D) Backfill `price_basis` from `display_gross` right away, no `NULL`.** Rejected for a minor: the
backfill freezes the coupling as a snapshot, so flipping `display_gross` afterwards silently changes
charged amounts; it forces the deferred `'gross'` basis into scope immediately; and blue-green needs a
static DB default that is wrong for half the rows written by field-unaware code. Right move at the major,
see end state above.

## Consequences

- Merchants can opt into fixed net proceeds with gross display, per customer group.
- Derived gross prices vary by country and are not psychological prices (net 10.00 € shows as 11.90 € in
  DE, 12.00 € in AT). Needs prominent admin help text and merchant docs.
- `GrossPriceCalculator`'s `isCalculated = false` path becomes load-bearing and needs promoted test
  coverage (list, regulation and reference prices, cash rounding intervals).
- Fully backwards compatible: nullable column, additive service, additive API field, no template or
  schema changes, no feature flag needed.
- Release docs: RELEASE_INFO entry and changelog, no UPGRADE entry for this iteration.
- Enabled follow-ups: charm-price rounding of derived gross prices, the `'gross'` basis, an exact-net
  mode as a third basis value.

## Pseudo-code

```php
// PriceSelector::select(Price $price, SalesChannelContext $context): SelectedPrice
$basis = $context->getCurrentCustomerGroup()->getPriceBasis();
$displayGross = $context->getTaxState() === CartPrice::TAX_STATE_GROSS;

if ($basis === null) { // legacy: basis follows display mode
    return new SelectedPrice($displayGross ? $price->getGross() : $price->getNet(), isCalculated: true);
}

// PRICE_BASIS_NET: net is authoritative, gross display derives the gross live
return new SelectedPrice($price->getNet(), isCalculated: !$displayGross);
```
