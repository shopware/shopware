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

* Since [#20741](https://github.com/shopware/shopware/pull/20741) the context factory can build the `ShippingLocation` of a context without a customer from an address object instead of only a country. Today this is only used to assemble the context of an existing order.
* `sales_channel_api_context`, where `PATCH /store-api/context` already stores its payload, has a bounded lifetime: rows expire after `lifetimeInterval` (default `P1D`) and are deleted by a scheduled cleanup task. Anything written to the context payload — including today's `countryId`/`countryStateId` for a visitor who never orders — already shares this lifecycle.

Separately, headless clients today have to orchestrate several existing routes to get a full picture after every change: `PATCH /store-api/context` to change something, `GET /store-api/checkout/cart` for the recalculated cart, the shipping and payment method routes, `GET /store-api/checkout/gateway` for app-filtered methods, and `/store-api/shipping-cost/cart` for per-method costs. None of these return each other's data, so every address change that should update the UI turns into several round trips.

## Decision

Two independent problems, one piece of work:

1. **A visitor without a customer can carry a full address, a billing address, and a few honestly derivable "as if this visitor ordered as a new guest right now" signals** — without creating a customer or an address record, and without firing registration events.
2. **Headless clients get one route that bundles "change something about my checkout" with "give me the recalculated result"**, instead of orchestrating `PATCH /store-api/context` plus several read routes themselves.

Nothing about registering the real customer changes: that still happens exactly once, when the visitor confirms the order, through the existing registration and order routes.

### The `ProspectiveCustomer` struct

A new struct on `SalesChannelContext` (`getProspectiveCustomer(): ?ProspectiveCustomer`) holds what we know about a visitor who does not have a customer yet:

* `shippingAddress` — the address fields rules and tax rules read: country, country state, zip code, city, street.
* `billingAddress` — optional. Defaults to the shipping address when omitted.
* `customerGroupId` — optional, defaults to the sales channel's default customer group.

It is stored in the existing `sales_channel_api_context` payload. No customer row, no address row, `customer_id` stays `NULL`.

**Validation.** Only `shippingAddress.countryId` is required, so that partial addresses work while the visitor is still typing. A given zip code is validated per country with the same constraint as customer addresses. A given country state must belong to the country. Violations are returned as the standard Store API validation error with a `source.pointer` per field (e.g. `/shippingAddress/zipcode`), the same format `POST /store-api/account/register` uses.

**Logged-in customers.** The struct only exists for contexts without a customer. Sending `shippingAddress`/`billingAddress` for a logged-in customer is rejected; they use `shippingAddressId`/`billingAddressId` as today.

**Lifecycle at login and registration.** The context payload survives login and registration. Without further handling, the stored prospective address would then take precedence over the customer's default address whenever no address id is set. Therefore:

* The `ProspectiveCustomer` is removed from the payload when a customer logs in or registers.
* The context factory only reads it when the context has no customer.

The registered customer's data always wins. Cart, selected shipping method and selected payment method are kept, as today.

**`ShippingLocation` is not changed.** It is read by the shipping rules, address validation, the delivery builder, the order converter and the tax rule filters, and it is serialized into the public cart response (`cart.deliveries[].location`). Instead, the context factory gains a second source for it: when there is no customer, the `ShippingLocation` is built from `ProspectiveCustomer::shippingAddress`. All consumers keep reading `$context->getShippingLocation()`.

**Caching.** The base context cache is keyed by country and country state only, so a full address must not end up in it. The `ShippingLocation` is built in the non-cached part of the factory, in the same place the existing injected-address handling lives, and a context with a `ProspectiveCustomer` is not cacheable there.

The Checkout Gateway app command `ChangeShippingLocationCommand` only resolves ISO codes to ids and writes them into the context parameters, and is not affected.

### Rule matching with a transient customer

Customer group, guest and history rules, and billing address rules, need a customer. This gap is closed for rule matching only, never for the context itself.

When the context has no customer but a `ProspectiveCustomer`, the checkout rule scope (`CheckoutRuleScope`, inherited by the cart and line item scopes) returns a transient, never persisted `CustomerEntity` built from it. It is built once per calculation with these values:

| Field | Value |
|---|---|
| `guest` | `true` |
| `active` | `true` — otherwise "inactive customer" rules would match prospects |
| `groupId` | the requested or default customer group |
| billing / shipping address | from the `ProspectiveCustomer`, billing defaulting to shipping |
| order count, order total, number of reviews | `0` |
| first login, last login, last order date | `null` |
| `email`, `firstName`, `lastName`, `customerNumber` | `''` |
| everything else nullable (account type, tags, custom fields, affiliate/campaign code, birthday, …) | `null` |

**The fallback only applies when a `ProspectiveCustomer` exists.** Every anonymous context without one — all sessions today, and every client that keeps only sending `countryId`/`countryStateId` — keeps evaluating rules without a customer, exactly as today. Without this gate, guest and history rules would start matching for every anonymous visitor on every existing shop as soon as this ships, silently changing which methods and promotions apply. Avoiding that is the main constraint of this design.

Flow conditions (`CustomerRuleScope`) always use a real customer and are not affected.

Code that loads the customer by id — including custom plugin rules — finds no row for the transient customer. This is an accepted limitation, the same as for the synthetic address the factory already builds for orders today.

### Rule coverage

There are 35 customer rules in `Shopware\Core\Checkout\Customer\Rule`. Cart rules do not read the customer and are all covered.

| Group | Rules | Without a customer |
|---|---|---|
| Shipping address | `ShippingCountryRule`, `ShippingStateRule`, `ShippingZipCodeRule`, `ShippingCityRule`, `ShippingStreetRule` | Evaluated against the address stored in the context |
| Billing address | `BillingCountryRule`, `BillingStateRule`, `BillingZipCodeRule`, `BillingCityRule`, `BillingStreetRule`, `DifferentAddressesRule` | Evaluated against the given billing address, otherwise assumed equal to the shipping address |
| Anonymous defaults | `CustomerGroupRule`, `CustomerLoggedInRule`, `IsGuestCustomerRule`, `IsActiveRule` | Evaluated as "requested or default customer group", "not logged in", "guest" and "active" |
| Customer history | `OrderCountRule`, `OrderTotalAmountRule`, `DaysSinceLastOrderRule`, `DaysSinceFirstLoginRule`, `DaysSinceLastLoginRule`, `NumberOfReviewsRule` | Evaluated against zero history |
| Customer data | `EmailRule`, `LastNameRule`, `CustomerSalutationRule`, `CustomerAgeRule`, `CustomerBirthdayRule`, `CustomerNumberRule`, `CustomerCustomFieldRule`, `CustomerTagRule`, `IsCompanyRule`, `IsNewsletterRecipientRule`, `CustomerCreatedByAdminRule`, `CustomerRequestedGroupRule`, `AffiliateCodeRule`, `CampaignCodeRule` | Not covered |

"Not covered" means the data these rules need — contact details, account type, tags, affiliate/campaign codes — does not exist before registration. They evaluate against empty or `null` values, which for positive conditions gives the same result as for a visitor without a customer today. Outside of rules, two more things depend on a customer and are not part of this: the tax exemption for companies, and promotions restricted to specific customers.

The order is always calculated again with the registered customer, so this gap can change what the visitor sees before the order, not what is ordered — see "Customer registration and order placement" for why that still needs an explicit step.

### New fields on `PATCH /store-api/context`

`ContextSwitchRoute` gains the fields `shippingAddress`, `billingAddress` and `customerGroupId`, which write the `ProspectiveCustomer`. It stays the single place where the context is changed, so `SwitchContextEvent` and `SalesChannelContextSwitchEvent` fire for these fields like for every other context change. Its behaviour for existing fields, including silently ignoring unknown keys, is unchanged.

### The `CheckoutDraft` route

A new Store API route that bundles a context change with the recalculated result:

```
GET   /store-api/checkout/draft
PATCH /store-api/checkout/draft
```

`PATCH` accepts every field `PATCH /store-api/context` accepts — `shippingMethodId`, `paymentMethodId`, `billingAddressId`, `shippingAddressId`, `countryId`, `countryStateId`, `currencyId`, `languageId`, and the new `shippingAddress`, `billingAddress`, `customerGroupId` — and delegates the change to `ContextSwitchRoute`. Unlike `PATCH /store-api/context`, unknown keys are rejected with `400`, so a client never gets a successful response for a change that did not happen.

`GET` has no side effects. It never changes the context or the token.

Both verbs return the same response:

```json
{
  "cart": { "...": "..." },
  "prospectiveCustomer": { "shippingAddress": { "...": "..." }, "billingAddress": { "...": "..." }, "customerGroupId": "..." },
  "shippingMethod": { "...": "..." },
  "paymentMethod": { "...": "..." },
  "currency": { "...": "..." },
  "language": { "...": "..." },
  "shippingMethods": [ "..." ],
  "paymentMethods": [ "..." ],
  "shippingCosts": [ "..." ]
}
```

* `prospectiveCustomer` is the stored draft state, so a client can restore its checkout form after a page reload with the same context token instead of keeping its own copy. It is `null` for logged-in customers and for visitors who have not sent an address.
* `shippingMethods` and `paymentMethods` only contain methods available for the current context. Shipping methods include their `prices`. The lists do not accept custom criteria.
* There is no separate error list. Invalid input is answered with `400` and field pointers (see "Validation"). Everything about the cart, including methods blocked by the Checkout Gateway or a selected method that is no longer available, is reported in `cart.errors`, as today.

Optional parts are controlled by flags, so a plain address change stays cheap. None of the flags reimplement the existing mechanism; each one runs it:

| Flag | Default | Effect |
|---|---|---|
| `methods` | `true` | `false` skips both method lists; they are returned as `null`, the response shape stays the same |
| `shippingCosts` | `false` | Runs the existing shipping cost calculation and returns the cost per available shipping method in `shippingCosts`, otherwise `null` |
| `checkoutGateway` | `false` | Runs the existing Checkout Gateway (app-based method filtering, blocked-method cart errors) |
| `taxed` | `false` | Runs the tax providers, same flag as `GET /store-api/checkout/cart` |
| `contextGateway` + `appName` | `false` | `PATCH` only. Runs the existing app Context Gateway for the named, installed app |

`checkoutGateway`, `contextGateway` and `taxed` can make HTTP calls to apps or tax providers. `shippingCosts` recalculates the cart once per shipping method.

**Token changes.** A Context Gateway command such as `RegisterCustomerCommand` or `LoginCustomerCommand` can change the context token. Whenever the token changes, the response returns the new one in the `sw-context-token` header, like every other Store API route. Since `contextGateway` is only accepted on `PATCH`, a `GET` never changes the token.

#### Why not reuse an existing route as the entry point

* **`ContextSwitchRoute`** stays the route that changes the context, and `CheckoutDraftRoute` delegates to it. Changing its response to a recalculated bundle would make every currency or language switch pay for a cart calculation, and would change the response of an existing route.
* **`CheckoutGatewayRoute`** has one job: filtering payment and shipping methods through apps. Turning it into the general checkout endpoint mixes that with plain cart and context recalculation.
* **`ContextGatewayRoute`** is not a client-facing mechanism. It requires a named, installed app and calls that app's backend, which decides what happens.

### Rate limiting and client responsibilities

`PATCH /store-api/checkout/draft` gets its own rate limiter, applied when the request changes something that affects the calculation (address, shipping method, payment method). `GET` is not limited by it.

* The limit is keyed by context token, not by IP address. Server-side rendered clients send all visitors' requests from one IP, so an IP key would limit the whole shop together. Rotating the token to bypass the limit only yields an empty draft, which is cheap to calculate.
* When the limit is exceeded, the route answers with `429`, the standard Store API error code for rate limits, and a `Retry-After` header.
* The limit is a backstop. Clients are expected to debounce input, and to only pass `shippingCosts`, `checkoutGateway`, `contextGateway` or `taxed` when they are about to show a number that needs to be accurate.

### Customer registration and order placement

Nothing here changes when or how a real customer is created. The visitor can fill in address, shipping method and payment method, see totals and available methods, and leave at any point, without a customer, an event or a flow having fired.

The flow to an order:

1. The visitor fills in the address → `PATCH /store-api/checkout/draft` (debounced), with `checkoutGateway=true`, `shippingCosts=true` or `taxed=true` once the respective numbers are shown.
2. The visitor decides to order. The client collects what the draft never asked for — name, e-mail, guest or account — before anything is registered.
3. `POST /store-api/account/register` (unchanged) creates the customer. `GuestCustomerRegisterEvent` and customer flows fire now, as today. The `ProspectiveCustomer` is removed from the context; the registered customer's addresses are used from here on.
4. The client calls `GET /store-api/checkout/draft` once more, now with the real customer. Rules from the "Customer data" group and code that keys off the customer's id can now give different results, so totals and available methods can change. A selected method that is no longer available is reported in `cart.errors` as today; a changed total is visible in `cart.price`. The client must show this final state for the visitor to confirm, and not place the order against numbers calculated before the customer existed. Pre-order price transparency rules (e.g. EU consumer protection law on the content of a binding order button) make this a requirement, not just good UX.
5. `POST /store-api/checkout/order` (unchanged) places the order.

## Considered Approaches

### A dedicated stateless preview route

An earlier draft of this ADR added `POST /store-api/checkout/preview`: a stateless route that cloned the current cart, built a one-off context from the request's address, and returned the calculated cart alongside the available methods. It persisted nothing.

Rejected: it duplicated what the cart, method and shipping cost routes already do, its result could differ from the visitor's persisted cart, and every future address-aware consumer would have needed the same duplication again.

### Register a guest on the first address input

Needs no core change and every rule works.
Rejected: it writes a customer before the visitor decides to buy, fires registration events and flows, requires all registration fields up front, and needs an update call for every later change.

### Persist the raw address directly in the context payload, no dedicated struct

Simpler than introducing `ProspectiveCustomer`, with the same data lifecycle.
Rejected because the billing address and the customer group do not fit naturally as loose context parameters, and a typed struct gives the context factory and the rule scope one clear thing to read instead of a growing set of parameter keys.

### Transient customer set on the context

A `CustomerEntity` built from the request and assigned with `$context->setCustomer()`, visible everywhere the context is.
Rejected because it would affect every piece of code that reads `$context->getCustomer()`, not only rule matching: storefront and Store API code would treat the visitor as logged in, and plugins and apps would act on a customer that does not exist. The transient customer used for rule matching needs the same filler values, but limiting it to the rule scope keeps their effect to rule evaluation.

### Grow `ContextSwitchRoute`, `CheckoutGatewayRoute` or `ContextGatewayRoute` into the general entry point

Rejected — see "Why not reuse an existing route as the entry point".

## Consequences

### Positive

* Store API clients can show available methods, shipping costs per method, taxes and a larger set of customer-dependent rule results (guest status, order history, billing address) for a visitor without a customer, with one route instead of several.
* No customer data is written before the order, and no unused guests have to be cleaned up. The `ProspectiveCustomer` shares the context's existing lifetime and cleanup.
* `ShippingLocation`, its consumers, the Store API cart response and the Checkout Gateway app command stay unchanged.
* All context changes, including the new fields, go through `ContextSwitchRoute` and fire the existing events.
* Checkout Gateway, Context Gateway, tax providers and the existing routes stay usable on their own for clients that only need one of them.

### Negative / trade-offs

* Rules that need real customer data — e-mail, account type, tags, affiliate/campaign codes, birthday, … — stay unevaluated until registration, and totals can change at that point.
* The transient customer in rule matching carries filler values for non-nullable fields. Custom rules that read these fields see empty strings, and custom rules that load the customer by id find nothing.
* A client that turns on every flag pays for all of it on every call.

### Open points

* How a tax provider failure should behave for a draft without a customer and without an order has not been tested end-to-end.
* The concrete rate limit numbers need load testing.
* How commercial features and third-party plugins behave with a `ShippingLocation` built from a `ProspectiveCustomer`, and with a transient customer visible only to rule matching, has not been tested.
* Needs an UPGRADE/release-info entry with the implementation: shops using history, guest, active or billing address rules will see them apply earlier in the funnel once their frontend sends an address.
