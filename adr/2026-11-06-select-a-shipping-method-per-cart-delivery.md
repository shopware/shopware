---
title: Select a shipping method per cart delivery
date: 2026-11-06
area: checkout
tags: [cart, delivery, shipping, store-api]
---

## Context

A cart can hold several deliveries.
Core builds one, extensions regroup the positions into more, for example one per warehouse or one for the goods in stock and one for the rest.
The `SalesChannelContext` keeps one shipping method, the customer's default, and each delivery carries the method it is actually shipped with.

What's missing is a way for the customer to pick that method per delivery.
The context switch can't do it, because it changes the default for every delivery.
Store extensions that offer the choice today each bring their own storefront controller, a cart extension keyed by their own ids, cart persistence from inside the delivery builder and decorated restorers to keep the choice through the login.
Headless clients get nothing.

## Decision

Core stores the customer's choice on the cart and applies it in the cart calculation.
The context shipping method stays the default and is never touched by it.

### A delivery is addressed by its identifier

Each delivery has an `identifier` that names it within the cart and stays the same in the next calculation, like the id of a line item.
Core's single delivery is `default`, an extension that splits the cart names its deliveries itself, for example by warehouse id.
The selection is stored by that identifier.

The index in `cart.deliveries` was the other option, since it's what the cart response exposed.
But it shifts as soon as an item moves to another group, and a stored index then silently points at different goods.
The identifier survives regrouping.

Shipping discount deliveries have an identifier too, but they only mirror the delivery they discount, so they can't be selected.
Core's own delivery can, and a method selected for `default` wins over the context method until the selection is dropped, like for any other delivery.

### The selection lives on the cart

The selection is a small cart extension, `identifier => shippingMethodId`.
It's saved and loaded with the cart like every other cart extension and is part of the cart response, so a client sees what it picked.
On login it's merged into the customer's saved cart per delivery, and the guest's choice wins, because it's the one the customer just made at checkout.

### The delivery processor applies it

`DeliveryProcessor` loads the selected methods together with the context method.
Right after the deliveries are built, and before anything prices or validates them, it replaces the method of each delivery whose identifier has a selection.
The positions that don't have a delivery time of their own then get the delivery time of the selected method, so the dates match what the customer picked.
A position on its restock date stays on it, only the delivery time on top changes.

Availability isn't checked at this point.
A selected method that's inactive or whose availability rule doesn't match stays on the delivery, and the existing validation blocks the cart with a `ShippingMethodBlockedError` for it.
Switching silently to another method would ship the goods differently from what the customer chose.

A selection is dropped when no delivery carries its identifier any more, or when its method can't be loaded because it was deleted.
Order editing keeps the original deliveries and ignores the selection.

### Store API

```
PATCH /store-api/checkout/cart/delivery
{
    "deliveries": [
        { "identifier": "warehouse-a", "shippingMethodId": "<id>" },
        { "identifier": "warehouse-b", "shippingMethodId": null }
    ]
}
```

`null` drops the selection, so the delivery goes back to the context method.
The route answers with the recalculated cart, a 404 for an identifier that names no delivery of the cart or a shipping discount delivery, and a 400 for a method that isn't assigned to the sales channel.

## Consequences

- Extensions that split deliveries give them stable identifiers and split in the `DeliveryBuilder`.
  The selection is applied right after the build, so deliveries a later processor regroups lose their selection in the same calculation.
  They don't need their own route, storage or restorer decoration for the choice any more.
- A client that offers the context switch next to the choice has to drop the selection when the customer switches the context, otherwise the switch doesn't reach that delivery.
- The cart hash has to cover each delivery's shipping method, so a selection changed between confirm and order invalidates it.
- A cart without a selection behaves exactly as before, nothing extra is loaded.
- Rules matched for the whole cart keep seeing the context method, for example a payment method's availability rule or a promotion's cart condition.
  Only rules matched per delivery see a selected method.
- `/store-api/shipping-cost/cart` prices each method for the whole cart and ignores the selections, so a client can still show what every method costs.
- The selected method is still priced and validated per delivery like any other, so shipping costs and blocked errors stay in one place.
