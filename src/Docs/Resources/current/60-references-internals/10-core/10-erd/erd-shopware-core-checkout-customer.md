[titleEn]: <>(Customer)
[hash]: <>(article:internals_core_erd_checkout_customer)

[Back to modules](./../10-modules.md)

The central customer entity of Shopware 6. Is created through SalesChannel processes and used in the order and cart workflow.

![Customer](./dist/erd-shopware-core-checkout-customer.png)


### Table `customer`

The main customer table of the system and therefore the entry point into the customer management. All registered customers of any sales channel will be stored here. The customer provides a rich model to manage internal defaults as well as informational data. Guests will also be stored here.


### Table `customer_address`

The customer address table contains all addresses of all customers. Each customer can have multiple addresses for shipping and billing. These can be stored as defaults in `defaultBillingAddressId` and `defaultShippingAddressId` in customer entity itself.


### Table `customer_group`

Customers can be categorized in different groups. The customer group is used so processes like the cart can incorporate different rules.


[Back to modules](./../10-modules.md)
