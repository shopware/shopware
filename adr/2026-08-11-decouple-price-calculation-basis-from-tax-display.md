---
title: Decouple price calculation basis from tax display state
date: 2026-08-11
area: checkout
tags: [core, checkout, cart, tax, price, customer-group]
status: accepted
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
2. The conversion we need already exists in one direction. `QuantityPriceDefinition::$isCalculated = false`
   tells `GrossPriceCalculator` "this value is net, gross it up with the tax rules first". Today almost
   nothing sets that flag. `NetPriceCalculator` has no such flag; it only rounds.

## Decision

We add a price basis setting that decides which stored value is authoritative. The tax state keeps its
current meaning (tax math flavor + labels), so `display_gross` finally means what its name says. Both
fields are independent and all four combinations are supported.

- New nullable field `customer_group.price_basis`: `'net'`, `'gross'` or `NULL`.
  - `NULL` (default): everything works exactly as today, the basis follows the display mode.
  - `'net'`: the stored net value is always used. With gross display the price definition is passed with
    `isCalculated = false`, so `GrossPriceCalculator` derives the gross from the shipping country's tax
    rules.
  - `'gross'`: the stored gross value is always used. With net display the net is derived in the selection
    layer (gross minus the tax it contains at the applicable rules) and handed over as an already
    calculated value, so `NetPriceCalculator` only rounds it and stays untouched.
  - Tax-free deliveries always charge the stored net value, on every basis. Nothing is derived at 0%.
- A new decoratable `AbstractPriceSelector` service replaces the four duplicated gross-vs-net branches
  (`ProductPriceCalculator`, `DeliveryCalculator`, `CurrencyPriceCalculator`, the app-script price
  facades). It takes the applicable `TaxRuleCollection` along with the price, and returns the selected
  value plus the `isCalculated` flag.
- Nothing downstream changes. Cart math, orders, documents, tax reporting, rules, recalculation, both API
  schemas and all storefront templates keep their current shape, because the derived value enters the
  pipeline at the same point a stored one does today.

How exact is the derived flavor? The displayed value must be a valid cash amount, so it gets rounded, and
the non-authoritative side ends up within half a rounding unit per unit sold. That shrinks the per-country
variance from full VAT percentage points to sub-cent noise. Exactness to the cent on both sides would need
per-surface derivation with visible penny mismatches (Alternative A). We accept the sub-cent variance.

Loose ends handled:

- **Caching**: derived prices depend on the country's tax rates, not just the tax state. The HTTP cache
  cookie and the entity cache hash additionally include a fingerprint of the context's tax rules exactly
  when the basis flavor differs from the display flavor (`net` basis with gross display, `gross` basis
  with net display). A basis matching its display state and every tax-free context do not fragment the
  cache.
- **Sorting and filtering** keep using the stored gross column (a per-country value cannot be indexed).
  For linked prices that matches the derived value at the product's home tax rate; other countries drift
  by the rate delta.
- **Admin**: the customer group detail page offers the tax display and the price basis as two independent
  controls. Both write explicit values, so the 6.8 shape is produced from the start and no mapping between
  the two fields is needed.

End state: `NULL` is transitional. With v6.8 a migration backfills the remaining `price_basis` rows from
`display_gross`, the column becomes `NOT NULL`, and the `NULL` fallback in the selector goes away. Until
then `NULL` keeps the old coupling alive for every writer that does not know the field: old core during
blue-green, plugins, ERP syncs, API clients.

## Alternatives considered

**A) Second tax state on the context**: calculate on one flavor, derive the other per display surface.
Exact to the cent, but the displayed unit price times quantity no longer matches the charged total (up to
a cent per line), it cannot reconcile with vertical tax calculation at all, and it touches
`CalculatedPrice`, both API schemas, ~30 templates and every headless client. Not doable without a major.

**B) Display-only decoration in the product layer** (listing shows gross, cart stays net). Rejected:
listing and cart would show different prices.

**C) Global system config switch.** Cannot express mixed setups (B2C gross display + B2B net display,
both on a fixed net basis), and the display toggle already lives on the customer group.

**D) Backfill `price_basis` from `display_gross` right away, no `NULL`.** Rejected for a minor: the
backfill freezes the coupling as a snapshot, so flipping `display_gross` afterwards silently changes
charged amounts; and blue-green needs a static DB default that is wrong for half the rows written by
field-unaware code. Right move at the major, see end state above.

## Consequences

- Merchants can opt into fixed net proceeds with gross display, or a fixed customer-facing gross with net
  display, per customer group.
- Derived prices vary by country and are not psychological prices (net 10.00 € shows as 11.90 € in DE,
  12.00 € in AT). Needs prominent admin help text and merchant docs.
- `GrossPriceCalculator`'s `isCalculated = false` path becomes load-bearing and needs promoted test
  coverage (list, regulation and reference prices, cash rounding intervals).
- Fully backwards compatible: nullable column, additive service, additive API field, no template or
  schema changes, no feature flag needed.
- Release docs: RELEASE_INFO entry, plus an UPGRADE entry for the deprecated admin block.
- Enabled follow-ups: charm-price rounding of derived prices, an exact mode as a third basis value.

## Pseudo-code

```php
// PriceSelector::select(Price $price, TaxRuleCollection $taxRules, SalesChannelContext $context): SelectedPrice
$taxState = $context->getTaxState();

return match ($context->getCurrentCustomerGroup()->getPriceBasis()) {
    // net is authoritative, gross display derives the gross via GrossPriceCalculator
    CustomerGroupEntity::PRICE_BASIS_NET => new SelectedPrice(
        $price->getNet(),
        isCalculated: $taxState !== CartPrice::TAX_STATE_GROSS
    ),
    // gross is authoritative, net display takes out the contained tax here, tax-free charges the stored net
    CustomerGroupEntity::PRICE_BASIS_GROSS => match ($taxState) {
        CartPrice::TAX_STATE_GROSS => new SelectedPrice($price->getGross(), isCalculated: true),
        CartPrice::TAX_STATE_FREE => new SelectedPrice($price->getNet(), isCalculated: true),
        default => new SelectedPrice(
            $price->getGross() - $taxCalculator->calculateGrossTaxes($price->getGross(), $taxRules)->getAmount(),
            isCalculated: true
        ),
    },
    // null and unknown values follow the display mode (legacy)
    default => new SelectedPrice(
        $taxState === CartPrice::TAX_STATE_GROSS ? $price->getGross() : $price->getNet(),
        isCalculated: true
    ),
};
```
