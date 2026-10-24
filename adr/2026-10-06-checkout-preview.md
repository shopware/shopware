---
title: Checkout for visitors without a registered customer
date: 2026-10-06
area: checkout
tags: [checkout, store-api, cart, rule, sales-channel-context, headless]
---

## Context

Store API clients, such as headless storefronts and one-page checkouts, want to show the available shipping and payment methods, shipping costs and taxes as soon as a visitor enters a shipping address.
At that point no customer exists yet: the visitor is not logged in and has not been registered as a guest.

Shopware evaluates rules while it calculates the cart.
The matched rule ids are stored on the `SalesChannelContext`, and the shipping and payment method routes filter against them.
Limiting the available methods by address is therefore not a separate lookup, it needs a cart calculation with a context that knows the address.

Without a customer, the context only knows a country and optionally a country state:

* `PATCH /store-api/context` accepts `countryId` and `countryStateId` for visitors who are not logged in. `shippingAddressId` and `billingAddressId` require a logged-in customer.
* The `ShippingLocation` of such a context has no address. Rules on zip code, city and street, and tax rules on zip codes, do not match.
* Every customer-related rule (order history, guest status, billing address, …) treats a context without a customer as "no match for a positive condition", regardless of what the visitor has typed into the checkout form.

The only way to get a full address — or any of the above rules — into the context today is to register a guest.
A client that wants address-based results early therefore has to write a customer on the first address input. That fires `GuestCustomerRegisterEvent` and customer flows, requires every field the registration validates, and leaves unused guests behind when the visitor leaves.

Two things already in the codebase make a smaller change possible:

* Since [#20741](https://github.com/shopware/shopware/pull/20741) the context factory accepts an address object through the options `SalesChannelContextService::SHIPPING_ADDRESS` and `BILLING_ADDRESS`. Without a customer it builds the `ShippingLocation` from that address. The options are `@internal` and only used today to assemble the context of an existing order.
* `sales_channel_api_context`, where `PATCH /store-api/context` already stores its payload, has a bounded lifetime. `SalesChannelContextPersister` marks a row `expired` after `lifetimeInterval` (default `P1D`), and the scheduled `CleanupSalesChannelContextTaskHandler` hard-deletes rows older than the configured retention. Anything written to the context payload — including today's `countryId`/`countryStateId` for a visitor who never orders — already shares this lifecycle.

Separately, headless clients today have to orchestrate several existing routes to get a full picture after every change: `PATCH /store-api/context` to change something, `GET /store-api/checkout/cart` for the recalculated cart, `GET /store-api/checkout/gateway` for app-filtered methods, optionally `GET /store-api/context/gateway` for app-driven context changes. None of these return each other's data, so every address keystroke that should update the UI turns into several round trips.

## Decision

Two independent problems, one piece of work:

1. **A visitor without a customer can still carry a full address, a billing address, and a few honestly-derivable "as if this visitor ordered as a new guest right now" signals** — without ever creating a customer, an address record, or firing registration events.
2. **Headless clients get one route that bundles "change something about my checkout" with "give me the recalculated result"**, instead of orchestrating `PATCH /store-api/context` plus several read routes themselves.

Nothing about registering the real customer changes: that still happens exactly once, when the visitor confirms the order, through the existing registration and order routes.

### The `ProspectiveCustomer` struct

A new struct, attached to `SalesChannelContext` (`$context->getProspectiveCustomer(): ?ProspectiveCustomer`), holds what we know about a visitor who does not have a customer yet:

* `shippingAddress` — the address fields rules and tax rules read: country, country state, zip code, city, street.
* `billingAddress` — optional. Defaults to the shipping address when omitted, same assumption the "Rule coverage" section below already relies on.
* `customerGroupId` — optional, defaults to the sales channel's default customer group.
* `guest: true`, `firstOrder: true` — fixed; a visitor who has no customer yet is by definition about to order as a new guest, with no order history.

Nothing here is persisted beyond what `PATCH /store-api/context` (and its superset described below) already persists: the struct is stored in the same `sales_channel_api_context` JSON payload, no customer row, no address row, `customer_id` stays `NULL`.

**`ShippingLocation` is not touched, let alone deprecated.** It is used in 29 Core files today, including the five shipping rules, `AddressValidator`, `DeliveryBuilder`, `OrderConverter`, four `TaxRuleType` filters, and — more importantly — it is what `Delivery::getLocation()` serializes into the public `GET /store-api/checkout/cart` response (`cart.deliveries[].location`). Changing or removing it would be a Store API breaking change with no upside. Instead, `BaseSalesChannelContextFactory` gains a second source for `ShippingLocation`: when there is no customer, it is built from `ProspectiveCustomer::shippingAddress` instead of (or in addition to) the per-request `SHIPPING_ADDRESS` factory option it already supports. Every one of the 29 consumers keeps calling `$context->getShippingLocation()` unchanged.

This also does not touch the Checkout Gateway app command `ChangeShippingLocationCommand`: its handler never references the `ShippingLocation` class, it only resolves ISO codes to ids and writes them into the same parameter array `PATCH /store-api/context` already persists. That app contract is unaffected.

### Rule matching with a transient customer

Two gaps remain once the address is in place: customer-group/guest/history rules, and billing-address rules. Both are closed the same way, scoped to rule matching only, never to the context itself.

`RuleCollection::filterMatchingRules()` builds a `CartRuleScope` from `$cart` and `$context`; line-item and promotion rules go through `LineItemScope`. Both inherit `getCustomer()` unmodified from `CheckoutRuleScope`, which reads `$context->getCustomer()` — always `null` without a customer. `CheckoutRuleScope::getCustomer()` gets a fallback:

```php
class CheckoutRuleScope extends RuleScope
{
    public function getCustomer(): ?CustomerEntity
    {
        if ($this->context->getCustomer()) {
            return $this->context->getCustomer();
        }

        return $this->context->getProspectiveCustomer()?->asCustomerEntity();
    }
}
```

`asCustomerEntity()` builds a transient, never-persisted `CustomerEntity` once per calculation: `guest = true`, `orderCount = 0`, the resolved customer group, the (defaulted) billing address, every date-based history field (`firstLogin`, `lastLogin`, last order date) left `null`. Everything that cannot be honestly derived — account type, tags, custom fields, affiliate/campaign code, name, e-mail, birthday — stays unset, so the rules reading them keep matching the way they do for `null` today.

**This fallback only fires when `getProspectiveCustomer()` returns something** — i.e. only for a visitor who went through the new flow below. A context without a customer and without a `ProspectiveCustomer` — every anonymous session today, and every one that still only sends `countryId`/`countryStateId` tomorrow — keeps getting `getCustomer() === null` in rule matching, byte for byte like today. Without this gate, `IsGuestCustomerRule` and the history rules would start matching for every anonymous visitor on every existing shop the moment this ships, silently changing which payment methods, shipping methods and promotions apply before checkout even starts — that is the one real way to turn this into a breaking change, and it is the one thing this design is built around avoiding.

`CustomerRuleScope` (used by Flow condition matching) already overrides `getCustomer()` unconditionally with a real customer and is not affected. No other direct consumer of `CheckoutRuleScope` exists in Core.

Code that re-reads the customer by id — including custom plugin rules — still gets no result for this id, because it is synthetic and matches no row. That is a known, accepted limitation, same as for the synthetic `CustomerAddressEntity` the `SHIPPING_ADDRESS` factory option already builds today.

### Rule coverage

There are 35 customer rules in `Shopware\Core\Checkout\Customer\Rule`. Cart rules do not read the customer and are all covered.

| Group | Rules | Without a customer |
|---|---|---|
| Shipping address | `ShippingCountryRule`, `ShippingStateRule`, `ShippingZipCodeRule`, `ShippingCityRule`, `ShippingStreetRule` | Evaluated against the address stored in the context |
| Billing address | `BillingCountryRule`, `BillingStateRule`, `BillingZipCodeRule`, `BillingCityRule`, `BillingStreetRule`, `DifferentAddressesRule` | Evaluated against the request's billing address when given, otherwise assumed equal to the shipping address |
| Anonymous defaults | `CustomerGroupRule`, `CustomerLoggedInRule`, `IsGuestCustomerRule` | Evaluated as "default (or requested) customer group", "not logged in" and "guest" |
| Customer history | `OrderCountRule`, `OrderTotalAmountRule`, `DaysSinceLastOrderRule`, `DaysSinceFirstLoginRule`, `DaysSinceLastLoginRule`, `NumberOfReviewsRule` | Evaluated against zero history (count/amount `0`, no login or order dates) |
| Customer data | `EmailRule`, `LastNameRule`, `CustomerSalutationRule`, `CustomerAgeRule`, `CustomerBirthdayRule`, `CustomerNumberRule`, `CustomerCustomFieldRule`, `CustomerTagRule`, `IsCompanyRule`, `IsActiveRule`, `IsNewsletterRecipientRule`, `CustomerCreatedByAdminRule`, `CustomerRequestedGroupRule`, `AffiliateCodeRule`, `CampaignCodeRule` | Not covered |

"Not covered" means the rule behaves exactly as it did for every visitor who is not logged in before this change. We accept this remaining gap: the data these rules need — account type, tags, affiliate/campaign codes, contact details — genuinely does not exist before registration. Outside of rules, two more things depend on a customer and are not part of this: the tax exemption for companies, and promotions restricted to specific customers.

The order is always calculated again with the registered customer at confirmation, so this gap can change what the visitor sees before the order, not what is ordered — see "Final confirmation" below for why that still needs an explicit step.

### The `CheckoutDraft` route

A new Store API route, orthogonal to the existing Checkout Gateway and Context Gateway rather than replacing them:

```
GET   /store-api/checkout/draft
PATCH /store-api/checkout/draft
```

PATCH accepts the full union of what `PATCH /store-api/context` already accepts (`shippingMethodId`, `paymentMethodId`, `currencyId`, `languageId`) plus the new, checkout-specific fields that populate `ProspectiveCustomer` (`shippingAddress`, `billingAddress`, `customerGroupId`). Internally it persists through the same `SalesChannelContextPersister` call `ContextSwitchRoute` uses — no second, competing mutation path. `ContextSwitchRoute` (`PATCH /store-api/context`) is unchanged and stays available for clients that only want to switch currency or language without paying for a bundled recalculation.

Both verbs return the same curated response, never the raw `SalesChannelContext` (no existing route returns it; it carries the internal `Context` object, including its source, which was never meant for Store API clients):

```json
{
  "cart": { "...": "..." },
  "shippingMethod": { "id": "...", "name": "..." },
  "paymentMethod": { "id": "...", "name": "..." },
  "currency": { "...": "..." },
  "language": { "...": "..." },
  "shippingMethods": [ "..." ],
  "paymentMethods": [ "..." ],
  "errors": [ "..." ]
}
```

`cart` is resolved the same way `CheckoutGatewayRoute` and `CartLoadRoute` already get it — a `Cart $cart` controller argument is calculated by the existing value-resolver before the method body runs. Because `CheckoutDraftRoute`'s PATCH persists the context change first and only then needs the fresh cart, it recalculates explicitly after the persist-and-refresh step already present in `ContextSwitchRoute`, rather than relying on the auto-injected argument (which would still hold the pre-patch context).

Three existing extension mechanisms are composed in, each through its own opt-in flag, none of them reimplemented:

* `checkoutGateway=true` — also runs the existing `CheckoutGatewayRoute` logic (app-based method filtering, blocked-method cart errors).
* `contextGateway=true&appName=…` — also runs the existing, app-authored Context Gateway hook (`/store-api/context/gateway`, `AppContextGateway`) for the named, merchant-installed app. This is unrelated to anything else in this ADR: it is a pre-existing extensibility point where an installed app can send back structured commands (`ChangeShippingAddressCommand`, `RegisterCustomerCommand`, `LoginCustomerCommand`, …) over an outbound webhook. `CheckoutDraftRoute` only gives a client the option to trigger it in the same round trip; it does not change what it does or who may use it.
* `taxed=true` — runs `TaxProviderProcessor`, same flag and same opt-in-by-default behaviour as `GET /store-api/checkout/cart` today.

All three can call out over HTTP (to apps or external tax providers). None of them run by default — a plain `PATCH /store-api/checkout/draft` with just an address change stays as cheap as today's `PATCH /store-api/context`.

#### Why not reuse an existing route as the entry point

* **`CheckoutGatewayRoute`** is documented for one job: *"validate payment and shipping methods based on the current cart"*. Growing it into the general "give me everything" polling endpoint overloads a narrowly-named route with a responsibility it was never designed for, and conflates app-based method filtering with plain cart/context recalculation.
* **`ContextGatewayRoute`** (`/store-api/context/gateway`) is not a client-facing mechanism at all. It requires a named, installed, explicitly-permissioned app and makes an outbound HTTP call to that app's backend, which decides what happens. A plain headless client with no such app configured gets `appNotFoundByName`. It solves a different problem (letting a specific app react) for a different actor (the app, not the client).
* **`ContextSwitchRoute`** could have grown an opt-in "give me the recalculated bundle back" response instead of a new route. We considered this, but a route scoped to checkout (and named for it) reads clearer to headless clients than an increasingly overloaded generic context endpoint, and keeps `ContextSwitchRoute`'s existing, simple response untouched for non-checkout callers.

### Rate limiting and client responsibilities

Setting the address (or anything else on the draft) can trigger a full cart recalculation, and — only when asked for — Checkout Gateway, Context Gateway or tax provider calls to apps.

* `PATCH /store-api/checkout/draft` is rate limited specifically when fields that change calculation (address, shipping method, payment method) are part of the request, separate from read-only `GET` calls.
* This is a backstop, not the primary defense. Clients are expected to debounce input themselves and only call `PATCH` once input has settled, and to only pass `checkoutGateway`/`contextGateway`/`taxed` when they are about to show the visitor a number that needs to be accurate, not on every intermediate keystroke.

### Customer registration and order placement stay separate and deferred

Nothing here changes when or how a real customer is created. The entire point of not touching `$context->setCustomer()` is that the visitor can fill in address, shipping method and payment method, see accurate totals and available methods, and abandon at any point, without a customer, an event, or a flow ever having fired.

The flow to an actual order:

1. Visitor fills in address → `PATCH /store-api/checkout/draft` (debounced), optionally with `checkoutGateway=true` once they open a method picker, `taxed=true` once a final number needs to be accurate.
2. Visitor decides to order. At this point the client still has to collect what the draft flow deliberately never asked for — name, e-mail, and guest-vs-account choice — before anything is registered.
3. `POST /store-api/account/register` (unchanged) creates the real customer. `GuestCustomerRegisterEvent` and customer flows fire now, for the first time, exactly as they do today — just later than before.
4. The client re-reads `GET /store-api/checkout/draft` once more, now with a real customer in context. Because the "Customer data" rule group and anything a plugin keys off the real customer's id can differ from what the `ProspectiveCustomer` fallback assumed, the totals and available methods the visitor is about to confirm can legitimately change at this step. The client must show this final, real state and have the visitor confirm it — not silently place the order against numbers computed before the customer existed. Pre-order price transparency rules (e.g. EU consumer protection law on the content of a binding order button) make this a correctness requirement, not just good UX.
5. `POST /store-api/checkout/order` (unchanged) places the order.

## Considered Approaches

### A dedicated stateless preview route

An earlier draft of this ADR added `POST /store-api/checkout/preview`: a stateless route that cloned the current cart, built a one-off context from the request's address via the `SHIPPING_ADDRESS` factory option, and returned the calculated cart alongside the available methods. It persisted nothing.

Rejected: it duplicated what `GET /store-api/checkout/cart`, the shipping/payment method routes and `ShippingCostRoute` already do, it did not fix the mismatch between the preview and the visitor's persisted cart, and every future address-aware consumer would have needed the same duplication again.

### Register a guest on the first address input

Needs no core change and every rule works.
Rejected: it writes a customer before the visitor decides to buy, fires registration events and flows, requires all registration fields up front, and needs an update call for every later change.

### Persist the raw address directly in the context payload, no dedicated struct

Simpler than introducing `ProspectiveCustomer`, and the data safety argument is the same (shares the context's existing TTL and cleanup).
Rejected in favour of a typed struct because the billing address and the guest/first-order/customer-group signals do not fit naturally as loose context parameters, and a struct gives `BaseSalesChannelContextFactory` and `CheckoutRuleScope` one clear, typed thing to read instead of a growing bag of parameter keys.

### Transient customer written to `$context->setCustomer()`

A `CustomerEntity` built from the request and assigned directly to the context, visible everywhere the context is.
Rejected because plugins and apps that load the customer by its id get no result, every non-nullable field of the entity has to be filled with made-up values, and this would apply to every piece of code that reads `$context->getCustomer()`, not only rule matching. Scoping the fallback to `CheckoutRuleScope::getCustomer()` instead keeps the blast radius to rule evaluation, where a synthetic customer is an accepted, existing pattern (see the synthetic `CustomerAddressEntity` built for `ShippingLocation` today).

### Grow `CheckoutGatewayRoute` or `ContextGatewayRoute` into the general entry point

Considered and rejected — see "Why not reuse an existing route as the entry point" above.

## Consequences

### Positive

* Store API clients can show available methods, shipping/payment costs, and now a materially larger set of customer-dependent rule outcomes (guest status, order history, billing address) for a visitor who has no customer, using one bundled route instead of orchestrating several.
* No customer data is written before the order, and no unused guests have to be cleaned up; the `ProspectiveCustomer` data shares the context's existing TTL and cleanup job.
* `ShippingLocation`, its 29 consumers, and the Checkout Gateway app command contract are all untouched — no deprecation, no migration.
* Extensions keep working without changes beyond the `CheckoutRuleScope` fallback.
* Headless clients get a single, checkout-scoped route for the entire pre-order loop, while Checkout Gateway, Context Gateway and Tax Provider remain independently usable for clients that only need one of them.

### Negative / trade-offs

* `CheckoutDraftRoute` duplicates `ContextSwitchRoute`'s parameter surface for shipping/payment method, currency and language, so the same mutation can now happen through two routes. This is a deliberate trade-off for headless ergonomics, not an oversight.
* Rules that need real customer data beyond "zero history, not logged in, default group, billing address, not yet registered" — account type, tags, affiliate/campaign codes, e-mail, birthday, … — remain unevaluated until registration, and totals can legitimately change at that point (see "Final confirmation").
* Apps and tax providers invoked through `CheckoutDraftRoute`'s toggles receive the same data they would through the existing routes; nothing new is exposed to them, but a client that always turns every toggle on pays for all of it on every debounced call.

### Open points

* How a tax provider failure should behave for a draft that has a `ProspectiveCustomer` but no real customer and no order yet has not been tested end-to-end.
* Whether `CheckoutDraftRoute`'s rate limit should share its budget with `PATCH /store-api/context`'s existing abuse protections or use a separate one — needs a number from load testing.
* How commercial features and third-party plugins behave with a `ShippingLocation` sourced from `ProspectiveCustomer`, and with a transient customer visible only to rule matching, has not been tested.
* Needs an UPGRADE/release-info entry: shops using history, guest, or billing-address rules will see those rules apply earlier in the funnel once their frontend adopts `CheckoutDraftRoute`.
