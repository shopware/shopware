---
title: Decouple price calculation basis from tax display state
date: 2026-08-11
area: checkout
tags: [core, checkout, cart, tax, price, customer-group]
status: proposed
---

## Context

Shopware stores every product price as a gross/net pair (`Price` struct), and a single string — the tax state
(`CartPrice::TAX_STATE_GROSS` / `TAX_STATE_NET` / `TAX_STATE_FREE`, resolved by `TaxDetector` from
`customer_group.display_gross` and the tax-free rules) — currently does triple duty:

1. **Price selection** — which stored value is authoritative
   (`ProductPriceCalculator::getPriceForTaxState()`, `DeliveryCalculator`, `CurrencyPriceCalculator`,
   the DAL/Elasticsearch price accessors).
2. **Tax math** — whether taxes are extracted from the price (`GrossPriceCalculator`) or added on top
   (`NetPriceCalculator`), and how `AmountCalculator` builds the cart total.
3. **Presentation** — which "incl./excl. VAT" labels the storefront, documents, and admin render.

The economic consequence of this coupling: a merchant with gross display (B2C) sells at a **fixed gross**
price. The included tax depends on the destination country's rate, so the merchant's net proceeds vary per
country (net 10.00 € stored, gross 11.90 € fixed → an Austrian order at 20 % nets only 9.92 €). A merchant
with net display (B2B) gets a fixed net, but customers see net prices.

Merchants have asked for the combination **"fixed net proceeds + gross display"**: calculate from the net
price so the merchant always earns the same amount, while B2C customers still see tax-inclusive prices.

Two findings from the current code shape this decision:

- **There is no display layer.** `CalculatedPrice` carries a single `unitPrice`/`totalPrice` in the
  context's tax state. Storefront templates print these numbers verbatim; only the VAT *labels* branch on
  the tax state (5 template spots on `context.taxState`, 4 on `price.taxStatus`). The Store API likewise
  exposes only the resolved value. "Display" is therefore not a place in the code we can toggle — it is
  whatever the calculation produced.
- **The conversion primitive already exists.** `QuantityPriceDefinition::$isCalculated = false` tells
  `GrossPriceCalculator` that the given value is net and must be grossed up via
  `TaxCalculator::calculateGross()` before taxes are extracted from it
  (`GrossPriceCalculator::getUnitPrice()`, also honored for list and regulation prices). Today this path is
  almost unreachable (`ProductPriceCalculator` always sets `isCalculated = true`); the admin's
  `PriceActionController` is its main consumer.

## Requirements

- A merchant can configure that the **net price is the authoritative calculation basis** while customers
  see **gross prices** (and symmetrically, the design should not preclude a fixed-gross basis with net
  display).
- Merchants opting in accept that displayed gross prices are **derived**: they vary with the destination
  country's tax rate and are generally not "psychological" prices (net 10.00 € → 11.90 € in DE, 12.00 € in AT).
- The change must be **non-breaking**: default behavior stays byte-identical, the new behavior is opt-in,
  and all extension surfaces (`AbstractTaxDetector`, price calculators, Store API schema, Twig blocks,
  order/document rendering, app scripts) keep their contracts.
- Orders, documents, tax reporting, and order recalculation must remain self-consistent — a customer must
  never see a gross line total that disagrees with the amount charged.

## Decision

We introduce a **price basis** setting that decouples *which stored price value is authoritative* from the
tax (display) state, and we implement it at the **price-selection stage** using the existing
`isCalculated` mechanism — not as a second tax state threaded through calculation and display.

### Concept

- The tax state keeps its current meaning end to end: it decides the tax math flavor and the presentation
  (gross display ⇒ `TAX_STATE_GROSS`, exactly as today). `customer_group.display_gross` finally means what
  its name says.
- A new nullable enum field `customer_group.price_basis` (`'net'` | `'gross'` | `NULL`) declares which
  stored price value is authoritative:
  - `NULL` (default): legacy behavior — the basis follows the tax state. Nothing changes.
  - `'net'`: the stored **net** value is always selected. When the tax state is gross, the price definition
    is handed to the calculators with `isCalculated = false`, so `GrossPriceCalculator` derives the gross
    price live from the net value and the tax rules of the customer's shipping country
    (`gross = cashRound(net × (1 + rate))`), then extracts taxes from that gross — identical math to a
    merchant who had maintained that gross value by hand. When the tax state is net or tax-free, the net
    value is used verbatim, as today.
  - `'gross'`: reserved for the symmetric case (fixed gross, net display). Out of scope for the first
    iteration because `NetPriceCalculator` intentionally ignores `isCalculated`; see "Extendability".
- Because the derivation happens *before* the cart math, everything downstream is untouched and stays
  internally consistent: `unitPrice × quantity == totalPrice` per line, `AmountCalculator` totals, order
  persistence (`taxStatus` remains `gross`), documents, ZUGFeRD, tax reporting, promotions, rules, the
  admin order module, and every Store API response keep their exact current shape and invariants.

### Precision of the "fixed net" guarantee

The merchant's realized net per unit is `g / (1 + r)` where `g = cashRound(net × (1 + r))` — i.e. the
stored net plus/minus at most half a rounding unit (default: half a cent) per unit, because the customer
facing gross must be a valid cash amount. This reduces the per-country variance from full percentage
points (today's fixed-gross behavior) to sub-cent rounding noise. An *exact* net-to-the-cent guarantee is
only achievable by making the whole cart net-authoritative and deriving gross figures per display surface,
which produces visible penny mismatches (displayed gross unit × quantity ≠ charged total) — see
Alternatives. We accept the sub-cent variance.

### Affected technical domains

**Core / DAL — new field.**
`CustomerGroupDefinition` gets `(new StringField('price_basis', 'priceBasis'))->addFlags(new ApiAware(),
new Since('6.7.x.0'))` with constants `CustomerGroupEntity::PRICE_BASIS_NET / PRICE_BASIS_GROSS`, plus a
non-destructive migration adding a nullable column (precedent:
`Migration1782308630AddBusinessTimeZoneToSalesChannel`). Values are validated by a write-command validator
or an `EnumField`-style allowlist.

**Cart / price selection — one new decoratable service.**
The gross-vs-net selection branch is currently duplicated across `ProductPriceCalculator`
(`getPriceForTaxState()`, also for list, regulation, and cheapest prices), `DeliveryCalculator`,
`CurrencyPriceCalculator`, and the app-script facades (`PriceFacade`, `PriceCollectionFacade`). We
consolidate these call sites onto a new `AbstractPriceSelector` service:

- Input: the stored `Price` (or currency price collection) and the `SalesChannelContext`.
- Output: the selected float value plus a `requiresGrossConversion` flag that the call site maps to
  `QuantityPriceDefinition::$isCalculated = false`.
- Default implementation: legacy behavior for `priceBasis = NULL`; for `priceBasis = 'net'` it returns the
  net value, with conversion required only when the tax state is gross.

`QuantityPriceCalculator`, `GrossPriceCalculator`, `NetPriceCalculator`, `AmountCalculator`,
`TaxCalculator`, and `CartRuleLoader` are **not modified**. The tax-free threshold re-detection in
`CartRuleLoader::validateTaxFree()` keeps working: when it flips the context to `TAX_STATE_FREE` and
re-processes, the selector serves the net value verbatim for the re-run — which is precisely the fixed-net
semantics.

**Order persistence & recalculation — no change.**
Orders persist `price.taxStatus = 'gross'` and the line items' `QuantityPriceDefinition` including
`isCalculated` and the resolved tax rules, so `RecalculationService` reproduces the same amounts.
Newly added line items during recalculation go through the same selector with the order-pinned context.

**Storefront — no template changes.**
Displayed values are already the calculated ones; the VAT labels already follow the tax state, which is
unchanged. The only follow-up is documentation: with `priceBasis = 'net'`, the stored gross value is not
displayed anywhere (it remains in use for sorting/filtering, see Consequences).

**Store API / Admin API — additive only.**
`customer_group.priceBasis` appears in both APIs (`ApiAware`); `context.taxState`, `CalculatedPrice`, and
`CartPrice` schemas are untouched. Headless frontends need no adaptation.

**Administration.**
`sw-settings-customer-group-detail` gets a "Price basis" single-select (Follows display mode [default] /
Always net / Always gross [disabled until implemented]) next to the existing gross-display toggle, with
help text explaining the derived-gross consequences. The product price fields get a hint when at least one
customer group overrides the basis, since manually maintained (unlinked) gross values are then ignored at
runtime.

**HTTP & entity cache — the one genuinely sharp edge.**
Today, gross prices are country-independent, so the cache hash (`CacheHeadersService` cookie,
`EntityCacheKeyGenerator::getSalesChannelContextHash()`) only carries the tax *state*. With a derived
gross, rendered prices additionally depend on the applicable tax **rates** of the shipping country. When
any customer group of the sales channel defines a `priceBasis`, both hashes must include a fingerprint of
the context's resolved tax rules (e.g. a hash over `SalesChannelContext::$taxRules` rates); countries with
identical rates keep sharing cache entries. This is an additive cache-key component, conditionally applied,
so existing shops see no cache fragmentation.

**Rules, promotions, app scripts — semantics unchanged, documented.**
`CartTaxDisplayRule` keeps matching the tax state. Absolute promotion values, custom line item prices, and
app-script price manipulations remain denominated in the display state (gross for B2C), as today; a
merchant's "10 € off" therefore stays 10 € gross. The `PriceFacade`/`PriceCollectionFacade` accessors
route through the selector so scripted price reads respect the basis.

### Extendability

- `AbstractPriceSelector` is the public extension point: plugins can decorate it to implement, e.g.,
  per-country basis strategies or charm-price rounding of the derived gross (rounding the derived value up
  to `x.90` — a frequently requested follow-up that this design enables cheaply).
- The `'gross'` basis (fixed gross, net display) is specified but deferred: it requires
  `NetPriceCalculator` to honor a net-conversion flag, which is a behavior change to a stable calculator
  and needs its own rounding analysis. The enum and selector are designed so this lands without schema
  changes.
- A future exact-net mode (Alternative B) could be introduced as a third basis value without conflicting
  with this design.

### Backwards compatibility assessment

- New nullable column + entity field: non-destructive, BC-safe (`Since` flag, migration test).
- New service + interface: additive. The replaced private selection methods are internal.
- `isCalculated = false` on gross calculation: existing, tested behavior of `GrossPriceCalculator`.
- No change to `Context::$taxState`, `AbstractTaxDetector`, calculator signatures, `CalculatedPrice`,
  `CartPrice`, order schema, document templates, or Twig blocks.
- Cache-key component: conditional on opt-in, additive event constant on `HttpCacheCookieEvent`.
- No feature flag required: with `priceBasis = NULL` everywhere, every code path resolves to today's
  behavior. Development can still hide the admin UI behind a named (toggleable, non-major) flag until the
  feature is complete.

## Alternatives considered

### A) Full dual tax state ("calculation state" + "display state" on the context)

Thread two states through `Context`/`SalesChannelContext`: calculate everything net-authoritative, widen
`CalculatedPrice` (and its field serializer, order schema, and Store API schema) with gross counterparts,
and teach every display surface (≈30 storefront templates, checkout summary, documents, admin order
module, headless clients) to pick the display-side value.

Rejected because:
- It guarantees exact net proceeds but **breaks display consistency**: the displayed gross unit price must
  be rounded, so `displayed unit × quantity` differs from the charged total by up to a cent per line
  (e.g. net 4.20 €, 19 %, qty 3 → displayed 3 × 5.00 € = 15.00 € vs charged 14.99 €), and the sum of line
  grosses cannot reconcile with the vertical tax calculation mode at all.
- The blast radius is the entire price pipeline plus both API schemas; headless frontends must adopt new
  fields; ~100 test files touch `taxStatus`/`TAX_STATE_*`. Not deliverable without a major, and arguably
  not without breaking the implicit contract that a `CalculatedPrice` is one number.
- Shopware 6's dual stored price was a deliberate correction of Shopware 5's derived-price rounding
  problems; reintroducing derivation *at every display edge* re-creates that problem class in a worse
  place (after calculation instead of before).

### B) Display-only decoration at the product layer

Keep the cart net, decorate `ProductPriceCalculator` (or use `ProductPriceCalculationExtension`) to attach
gross display prices in listings/detail pages.

Rejected: the cart, checkout, and order confirmation would show net values while the listing shows gross —
the exact mismatch customers notice and merchants cannot ship.

### C) Global system-config switch instead of a customer-group field

Simplest storage, but it cannot express the common mixed setup (B2C group with gross display + B2B group
with net display, both on a fixed net basis — which this design supports naturally), and the customer
group is where the existing display toggle lives; keeping both toggles on one entity keeps the mental
model coherent.

## Consequences

- Merchants can opt into fixed net proceeds with gross display per customer group. Their realized net
  varies only by sub-cent rounding instead of by the spread of EU VAT rates.
- Displayed gross prices become **country-dependent and non-psychological**; this is inherent to the
  requested economics and must be prominent in the admin help text and merchant documentation.
- With `priceBasis = 'net'`, the stored gross value is no longer displayed or charged. It **remains the
  sorting/filtering key** in DAL and Elasticsearch (`PriceFieldAccessorBuilder`, cheapest-price accessors,
  price listing filter), because a per-country derived gross cannot be indexed. For linked prices the
  stored gross equals the derived gross at the product's home tax rate, so sort order and filter bounds
  stay accurate for the default market and drift only by the rate delta for others (e.g. ~0.8 % for
  DE→AT). Merchants who unlink and hand-maintain gross values in this mode get misleading filter bounds —
  documented, and surfaced by the admin hint.
- The HTTP/entity cache becomes tax-rate-aware for opted-in shops (slightly finer-grained cache) and is
  untouched otherwise.
- `GrossPriceCalculator`'s `isCalculated = false` path becomes load-bearing and needs its test coverage
  promoted accordingly (list price, regulation price, reference price, zero-decimal and 0.05-interval cash
  rounding).
- The duplicated tax-state price-selection branches collapse into one decoratable service, removing four
  copies of the same `if`.
- Follow-ups enabled but not included: charm-price rounding of derived gross prices, the symmetric fixed
  gross basis, and an exact-net mode as a third basis value.
- Release documentation: RELEASE_INFO feature entry and changelog required; no UPGRADE entry (no
  third-party action needed).

## Pseudo-code

```php
// CustomerGroupEntity
public const PRICE_BASIS_NET = 'net';
public const PRICE_BASIS_GROSS = 'gross';
protected ?string $priceBasis = null;

// Core/Checkout/Cart/Price/AbstractPriceSelector
abstract class AbstractPriceSelector
{
    abstract public function select(Price $price, SalesChannelContext $context): SelectedPrice;
}

class PriceSelector extends AbstractPriceSelector
{
    public function select(Price $price, SalesChannelContext $context): SelectedPrice
    {
        $basis = $context->getCurrentCustomerGroup()->getPriceBasis();
        $displayGross = $context->getTaxState() === CartPrice::TAX_STATE_GROSS;

        if ($basis === null) {
            return new SelectedPrice(
                $displayGross ? $price->getGross() : $price->getNet(),
                isCalculated: true // legacy: stored value is authoritative
            );
        }

        // PRICE_BASIS_NET: net value is authoritative
        return new SelectedPrice(
            $price->getNet(),
            // gross display: let GrossPriceCalculator derive gross = net * (1 + rate)
            isCalculated: !$displayGross
        );
    }
}

// ProductPriceCalculator::buildDefinition() (analogous in DeliveryCalculator, CurrencyPriceCalculator)
$selected = $this->priceSelector->select($price, $context);

$definition = new QuantityPriceDefinition($selected->getValue(), $taxRules, $quantity);
$definition->setIsCalculated($selected->isCalculated());
```
