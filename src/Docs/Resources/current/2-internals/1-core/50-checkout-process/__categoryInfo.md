[titleEn]: <>(Checkout Process)
[hash]: <>(category:checkout)

The checkout process contains two central and business heavy units, the `Cart` and the `Order`. Other modules in the `Checkout` bundle handle cross cutting concerns in support of these modules. `Cart` and `Order` represent two distinct steps in the shopping workflow. The `Cart` represents the *purchase procedure*, while the `Order` represents the *purchase management*. The handover between these components is called *checkout*

## [Cart](./10-cart.md)

The following diagram illustrates the cart's context and shows the different system parts involved.

![Cart context](./dist/cart-component.png)

The `Cart` manages `LineItems` and applies calculations and filters, it represents a temporary model that collects possible order data and manages the validity of a single order. 

## [Order](./20-order.md)

The `Order` on the other hand represents a permanent model capturing the carts data and offering state management upon it.

![order context](./dist/order-component.png)

As you can see the basic ownership of the data moved from the customer to the system, since the order represents a contract that is managed by the shop itself. State management in this context is done through Shopware 6's state machine.

## Cross cutting concerns

Cross cutting in the checkout means that an order as well as the cart must know about and work on the following concepts:

### [Payment](./30-payment.md)

Shopware 6 comes with its own pluggable Payment handling. Only orders can be payed for. A list of payment handlers is managed by Shopware 6.

### Shipping

Contrary to payment, shipping is mostly managed through the Platform and does not rely on custom handlers. It adds a surcharge to the cart and handles state transitions in relation to a  `delivery`.

### Customer

The customer, or guest responsible for the order. A customer addresses for shipping and billing, as well as groups. Registration is possible in any sales channel.

### Printable Documents

Printable documents and PDF generation is used in order to create invoices, cancellations or offers.
