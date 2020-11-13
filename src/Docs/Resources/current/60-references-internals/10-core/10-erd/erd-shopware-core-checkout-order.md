[titleEn]: <>(Orders)
[hash]: <>(article:internals_core_erd_checkout_order)

[Back to modules](./../10-modules.md)

Order management of Shopware 6.
Notice: The data structure in this module is mostly decoupled from the rest of the system so deleting customers, products and other entities will not break already placed orders.

![Orders](./dist/erd-shopware-core-checkout-order.png)


### Table `order`

The root table of the order process.
Contains the basic information related to an order and relates to a more detailed model representing the different use cases.


### Table `order_address`

Stores the specific addresses related to the order. Denormalized so a deleted address does not invalidate the order.


### Table `order_customer`

The customer related to the order. Denormalized so a deleted customer does not invalidate the order.


### Table `order_delivery`

Represents an orders delivery information and state.
Realizes the concrete settings with which the order was created in the checkout process.


### Table `order_delivery_position`

Relates the line items of the order to a delivery. This represents multiple shippings per order.


### Table `order_line_item`

A line item in general is an item that was ordered in a checkout process.
It can be a product, a voucher or whatever the system and its plugins provide.
They are part of an order and can be related to a delivery and is related to order.


### Table `order_transaction`

A concrete possibly partial payment for a given order.
Is always related to a payment method and the state machine responsible for the process management.


[Back to modules](./../10-modules.md)
