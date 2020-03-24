[titleEn]: <>(Order)
[hash]: <>(article:checkout_order)

Once a cart is checked out an `Order` is created. The whole structure of the cart is stored [to the database](./../10-erd/erd-shopware-core-checkout-order.md). Contrary to the cart, a structure that allows a great degree of freedom and is *calculation optimized*, the order is **workflow optimized**.  

## Design goals

Denormalization
  : The Order itself does not depend on the catalogue or the products. The whole enriched line item is stored to the database, as well as all calculated prices. Orders only get recalculated if specifically triggered through the API.
  
Workflow dependant
  : Orders states change in a defined, predictable and configurable way - other state transitions are blocked.

## State management

During the order placement at least three distinct state machines are started.

* One concerning the [order as a whole](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Order/OrderStates.php)
* One concerning each [transaction](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Order/Aggregate/OrderTransaction/OrderTransactionStates.php)
* One concerning each [delivery](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Order/Aggregate/OrderDelivery/OrderDeliveryStates.php)

These can be used to track the progress during the order process and notify the customer about the current state of the order. 

*Note: The state machines displayed in the following sections can actually be modified through the API, this is just the default setup.*

### Order state machine

The default state machine for the whole order looks like this:

![order states](./dist/order-state-machine.png)

### Transaction state machine

Each payment looks like this:

![payment states](./dist/order-payment-state-machine.png)

### Delivery state machine

And finally the delivery state progression

![delivery states](./dist/order-delivery-state-machine.png)

