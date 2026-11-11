---
title: Evaluate shipping rules per cart delivery
date: 2026-11-05
area: checkout
tags: [cart, delivery, shipping, rule-builder]
---

## Context

A cart can hold several deliveries, and so can an order.
Each delivery already has its own shipping method, shipping location, positions, dates and costs, and the shipping price matrix by value, weight, quantity and volume already matches against the positions of the delivery it prices.
Rules do not.
The shipping address rules and the shipping method rule read the `SalesChannelContext`, which has exactly one shipping method and one shipping location.
`DeliveryCalculator` picks rule based price rows and `DeliveryValidator` checks the availability rule with `$context->getRuleIds()`, the ids of the rules that matched the whole cart.

A price row "free shipping to Germany" or an availability rule "no delivery to the islands" is therefore decided once per cart, by the context address, and then applied to every delivery, even when a delivery goes somewhere else with another shipping method.
A feature that splits one order into several deliveries can regroup positions in a plugin, but it cannot make the core rules or the two lookups delivery aware.
This has to happen in core, before `v6.8.0.0` closes.

## Decision

The `SalesChannelContext` keeps one shipping method and one shipping location.
They are the customer's defaults and the tax jurisdiction of the cart.
A delivery carries the effective shipping method and location, and from now on also the rules that match it.

### A delivery scope, not a new rule set

`DeliveryRuleScope` extends `CartRuleScope` and carries the `Delivery` being evaluated.
Rules that are about the cart keep working unchanged, because a delivery scope is a cart scope.
Only the rules that read the shipping location (country, state, zip code, street, city) or the shipping method get a branch: in a delivery scope they read the delivery, everywhere else they read the context as before.
The existing scopes stay as they are.
In a delivery scope the address rules read only the delivery's location.
The fallback to the customer's active shipping address, which the cart scope keeps for a location without an address, does not apply there, because that address can belong to another delivery.
Rule authors can adopt the same branch in their own rules; a rule without it evaluates the context default for every delivery, which is the behaviour of today.

### Rule ids live on the delivery

The matched rule ids are stored on the `Delivery` struct, next to the shipping method and location they belong to.
They are matched once per cart calculation in `DeliveryProcessor::process()`, after the deliveries are built and before they are priced, so the calculator and the validator find them on the object they already work with.
The scope's cart is the cart as it was last calculated, the same cart the context's rules were matched against, so a condition such as the cart amount means the same for the cart and for each delivery.
Both read the delivery's rule ids instead of the context's while `v6.8.0.0` is active.
A delivery that was never matched has no rule ids, and both fall back to the context ids.
Deliveries that a plugin builds or regroups itself therefore keep today's cart-wide behaviour instead of silently losing all rule based prices.
The rule ids are private to the struct and not part of the Store API cart response.

### Match only what the shipping method references

A delivery is not matched against the whole rule set but only against the rules its shipping method references: the availability rule, the rule ids of its price rows and their calculation rule ids.
Nothing else can change its price or availability.
The matcher walks the loaded `RuleCollection` in its priority order, so the order of ids a delivery carries is the order the calculator relies on when it picks the first matching price row.

When a cart has exactly one delivery and its shipping method and location equal the context's, which is every cart core builds today, the matcher copies `$context->getRuleIds()` and evaluates nothing.
This keeps the result of the single delivery case identical to today, including the priority order, and costs no rule evaluation.
A cart with several deliveries matches each of them, also the ones to the context's address with the context's shipping method, so a rule that reads the delivery decides the same way for every delivery of that cart.
Rules are loaded through the same `AbstractRuleLoader` the `CartRuleLoader` uses, lazily and memoized per request.
A single delivery cart that equals the context never loads them in the matcher, a cart with several deliveries reads the rule cache once more per request.
The memo is cleared on `kernel.reset` and whenever a rule is written.

### What stays cart scoped

The tax state, the customer and customer group, the currency, the payment method, the cart totals and the cart's shipping costs are properties of the cart and keep being evaluated once, against the context.
The shipping costs of a delivery are not known yet when its rules are matched.
A price row with the condition "cart amount from 100" still means the whole cart.
Per delivery thresholds by value, weight, quantity or volume belong to the price matrix, which already works per delivery.
The cart weight, cart volume and free delivery item conditions read the whole cart in a delivery scope too.
Their labels promise all products of the cart, and a merchant can't see in which scope a rule is matched, so they don't switch their meaning implicitly.
Line item conditions such as the dimensions, the weight or the longest side and girth of an item keep walking the whole cart's line items as well, so one bulky item still blocks or surcharges every delivery.
Letting the merchant choose between the cart and the delivery for these conditions, with an explicit option in the rule builder, is a follow-up.

The recalculation path of order editing (`SKIP_DELIVERY_PRICE_RECALCULATION`) keeps the original deliveries and does not re-match them, so it behaves as before.

## Consequences

- With `v6.8.0.0` inactive nothing changes: no matching runs, the deliveries carry no rule ids, calculator and validator read the context.
- With the flag active a single delivery cart yields the same prices and errors as today, because its delivery equals the context and reuses the context rule ids.
- A cart with deliveries that differ from the context in address or shipping method gets price rows and availability decided per delivery, with the error naming the blocked shipping method of that delivery.
- Custom rules that read `getShippingLocation()` or `getShippingMethod()` from the context should handle `DeliveryRuleScope`; until they do they decide by the context default for every delivery, documented in `UPGRADE-6.8.md`.
- In a cart with several deliveries every delivery is matched on its own, also those to the context's address with the context's shipping method, so a rule that reads the delivery decides per delivery for all of them.
- Deliveries have to be built or regrouped while `DeliveryProcessor` builds them to get per delivery rule ids; deliveries a later cart processor creates carry none and keep the context ids.
- The additional cost is bounded by the number of referenced rules per divergent delivery and one more rule cache read per request; carts of today pay nothing.
