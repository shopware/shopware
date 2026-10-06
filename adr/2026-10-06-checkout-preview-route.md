---
title: Stateless checkout preview route for visitors without a customer
date: 2026-10-06
area: checkout
tags: [checkout, store-api, cart, rule, shipping-location, sales-channel-context]
---

## Context

Store API clients, such as headless storefronts and checkout apps, want to show the available shipping and payment methods, shipping costs and taxes as soon as a visitor enters a shipping address.
At that point no customer exists yet: the visitor is not logged in and has not been registered as a guest.

Shopware evaluates rules while it calculates the cart.
The matched rule ids are stored on the `SalesChannelContext`, and the shipping and payment method routes filter against them.
Limiting the available methods by address is therefore not a separate lookup, it needs a cart calculation with a context that knows the address.

Without a customer, the context only knows a country and optionally a country state:

* `PATCH /store-api/context` accepts `countryId` and `countryStateId` for visitors who are not logged in. `shippingAddressId` and `billingAddressId` require a logged-in customer.
* The `ShippingLocation` of such a context has no address. Rules on zip code, city and street, and tax rules on zip codes, do not match.

The only way to get a full address into the context today is to register a guest.
A client that wants address based results early therefore has to write a customer on the first address input. That fires `GuestCustomerRegisterEvent` and customer flows, requires every field the registration validates, and leaves unused guests behind when the visitor leaves.

Two building blocks already exist:

* Since [#20741](https://github.com/shopware/shopware/pull/20741) the context factory accepts an address object through the options `SalesChannelContextService::SHIPPING_ADDRESS` and `BILLING_ADDRESS`. Without a customer it builds the `ShippingLocation` from that address. The options are `@internal` and only used to assemble the context of an existing order.
* `ShippingCostRoute` runs what-if calculations on a cloned cart with a random token and `CheckoutPermissions::SKIP_CART_PERSISTENCE`.

## Decision

We add a stateless Store API route that calculates the current cart for a shipping address sent in the request.
It persists nothing: no customer, no cart, no context.

### Route

`POST /store-api/checkout/preview` (`store-api.checkout.preview`)

Request:

```json
{
    "shippingAddress": {
        "countryId": "…",
        "countryStateId": "…",
        "zipcode": "…",
        "city": "…",
        "street": "…"
    },
    "taxed": false
}
```

* Only `countryId` is required, so the route can be called while the address is still incomplete.
* The route accepts the five address fields that rules and tax rules read. It takes no name, e-mail or phone number.
* A billing address is not accepted. Without a customer it has no effect on rules or taxes.
* The selected shipping method, payment method, currency and language are read from the context of the request. Clients keep changing them with `PATCH /store-api/context`.
* A context with a logged-in customer is rejected. Those clients use the regular cart routes, which already calculate with the customer's addresses.

Response:

* `cart`: the calculated cart with prices, taxes, deliveries and errors. Its token is random and cannot be used with other routes.
* `shippingMethods` and `paymentMethods`: the methods available for this address.

### Calculation

```php
private function _preview(Request $request, Cart $cart, SalesChannelContext $context): CheckoutPreviewRouteResponse
{
    // validated, with country and state loaded, all remaining entity fields initialized
    $address = $this->buildAddress($request, $context);

    $previewContext = $this->contextFactory->create($context->getToken(), $context->getSalesChannelId(), [
        ...$this->optionsOf($context), // currency, language, shipping method, payment method, domain
        SalesChannelContextService::SHIPPING_ADDRESS => $address,
    ]);
    $previewContext->addState(self::STATE_CHECKOUT_PREVIEW);

    $previewCart = clone $cart;
    // the preview must never address the visitor's persisted cart
    $previewCart->setToken(Uuid::randomHex());

    $behavior = new CartBehavior([CheckoutPermissions::SKIP_CART_PERSISTENCE => true]);
    $previewCart = $this->cartRuleLoader->loadByCart($previewContext, $previewCart, $behavior, true)->getCart();

    $methods = $this->checkoutGatewayRoute->load(new Request(), $previewCart, $previewContext);
    $previewCart->addErrors(...array_values($methods->getErrors()->getElements()));

    if ($request->request->getBoolean('taxed')) {
        $this->taxProviderProcessor->process($previewCart, $previewContext);
    }

    return new CheckoutPreviewRouteResponse($previewCart, $methods->getShippingMethods(), $methods->getPaymentMethods());
}
```

* **Context:** the address is passed to the existing factory options. The preview context is built per request and is never written to `sales_channel_api_context`.
* **Checkout Gateway:** always runs, so methods removed by apps or plugins are removed in the preview too.
* **Tax providers:** run only with `taxed: true`, like `GET /store-api/checkout/cart`. Without it, shops using a tax provider get the taxes of the built-in calculation.

### Changes to existing code

* The `SHIPPING_ADDRESS` option is no longer documented as "only for existing orders". It stays `@internal`: plugins use the route, not the option.
* A new context state marks a preview calculation. Extensions can check it to skip side effects such as reservations or tracking.
* The route is rate limited. It is callable without a login and triggers a full cart calculation plus outgoing requests to apps.

### Extensibility

The route follows [Replace abstract route classes with the extension event system](./2026-09-24-replace-abstract-route-classes-with-extension-events.md).
It has no abstract base class and publishes a `CheckoutPreviewRouteExtension` with the request, the cart and the context.
Plugins change the input in `.pre` and the result in `.post`.

Cart collectors, processors, validators, rules and Checkout Gateway handlers need no changes.
They receive a regular `SalesChannelContext` without a customer and a `ShippingLocation` with an address.

## Rule coverage

There are 35 customer rules in `Shopware\Core\Checkout\Customer\Rule`. Cart rules do not read the customer and are all covered.

| Group | Rules | In the preview |
|---|---|---|
| Shipping address | `ShippingCountryRule`, `ShippingStateRule`, `ShippingZipCodeRule`, `ShippingCityRule`, `ShippingStreetRule` | Evaluated against the address of the request |
| Anonymous defaults | `CustomerGroupRule`, `CustomerLoggedInRule` | Evaluated as "default customer group of the sales channel" and "not logged in" |
| Billing address | `BillingCountryRule`, `BillingStateRule`, `BillingZipCodeRule`, `BillingCityRule`, `BillingStreetRule`, `DifferentAddressesRule` | Not covered |
| Customer data | `EmailRule`, `LastNameRule`, `CustomerSalutationRule`, `CustomerAgeRule`, `CustomerBirthdayRule`, `CustomerNumberRule`, `CustomerCustomFieldRule`, `CustomerTagRule`, `IsCompanyRule`, `IsGuestCustomerRule`, `IsActiveRule`, `IsNewsletterRecipientRule`, `CustomerCreatedByAdminRule`, `CustomerRequestedGroupRule`, `AffiliateCodeRule`, `CampaignCodeRule` | Not covered |
| Customer history | `OrderCountRule`, `OrderTotalAmountRule`, `DaysSinceLastOrderRule`, `DaysSinceFirstLoginRule`, `DaysSinceLastLoginRule`, `NumberOfReviewsRule` | Not covered |

"Not covered" means the rule behaves as it does for every visitor who is not logged in: a positive condition does not match, and for most of these rules a negated condition matches.

We accept this gap.
The preview targets visitors who are about to order as a new guest. Their customer history is empty and their billing address usually equals the shipping address.
The order is always calculated again with the registered customer, so the gap can change what the visitor sees before the order, not what is ordered.

Outside of rules, two more things depend on a customer and are not part of the preview: the tax exemption for companies, and promotions restricted to specific customers.

## Considered Approaches

### Register a guest on the first address input

Needs no core change and every rule works.
Rejected: it writes a customer before the visitor decides to buy, fires registration events and flows, requires all registration fields up front, and needs an update call for every later change.

### Persist the address in the context

`PATCH /store-api/context` would accept address fields for visitors who are not logged in and store them in the context payload.
All existing routes would work unchanged and no new route would be needed.
Rejected because the address of an anonymous visitor would be stored in the database before they place an order.

### Transient customer in the `SalesChannelContext`

A `CustomerEntity` built from the request and never written would cover all customer rules.
Rejected because plugins and apps that load the customer by its id get no result, and because every non-nullable field of the entity has to be filled with made-up values.

### Transient customer in the rule scope only

The customer is handed to the rules through the rule scope, as `CustomerRuleScope` does for flows, while the context stays anonymous.
This avoids the id problem, but rules and plugins that read the customer from the context still do not see it.
Not needed for the current scope. It can be added to the preview route later without changing its contract.

## Consequences

### Positive

* Store API clients can show the available methods, shipping costs and taxes for an address without creating a customer.
* Address based rules and zip code tax rules work for visitors who are not logged in.
* No customer data is written before the order, and no unused guests have to be cleaned up.
* Extensions keep working without changes, because the preview context is a regular anonymous context.

### Negative / trade-offs

* The preview is not binding. Totals and available methods can differ from the order when a rule from the "not covered" groups applies. Clients must handle the difference when the order is placed.
* The persisted cart of the visitor is still calculated with the country only. `GET /store-api/checkout/cart` and the preview can return different totals for the same visitor.
* Every preview call sends the cart and the address to apps with a Checkout Gateway, and with `taxed: true` to tax providers. Clients should debounce address input.
* Apps and tax providers receive only the five address fields and no customer.
* The response contains the costs of the selected shipping method only. Costs of the other methods need a separate recalculation per method, as `ShippingCostRoute` does.

### Open points

* How the preview responds when a tax provider fails: the order route aborts, the preview could return the cart with the built-in taxes and an error.
* How commercial features and third-party plugins behave with a `ShippingLocation` that has an address but no customer has not been tested.
