---
title: Add a new Order total amount rule to Rule Builder
issue: NEXT-14949
---
# Core
* Added migration `Migration1624202045AddValueOfOrdersToCustomerTable` to add a column `order_total_amount` to `customer` table and update this column for old customers. 
* Added new property `valueOfOrders` in class `Shopware\Core\Checkout\Customer\CustomerEntity` which used to define a total amount of all orders of customer.
* Added new `OrderTotalAmountRule` in `Shopware\Core\Checkout\Customer\Rule`
* Changed function `fillCustomerMetaDataFields` in class `Shopware\Core\Checkout\Customer\Subscriber\CustomerMetaFieldSubscriber` to rewrite this function with using plain sql and update `orderTotalAmount` when customer create/update a new order.
___
# Administration
* Added new component `sw-condition-order-total-amount` in `/src/app/component/rule/condition-type`
* Added new rule condition `customerOrderTotalAmount` in `/src/app/decorator/condition-type-data-provider.decorator.js`
