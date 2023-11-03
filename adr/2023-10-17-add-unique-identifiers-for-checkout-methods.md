---
title: Introduction of Unique Identifiers for Checkout Methods
date: 2023-10-17
area: Checkout
tags: [payment, shipping]
---

## Context
In the current implementation, there exists a challenge for extension developers in uniquely identifying payment and shipping methods using identifiers.
This issue is particularly significant for app servers, as it necessitates calls to the Shopware Admin API for the identification of payment and shipping methods based on their respective IDs.

## Decision
We will introduce a new property called `technicalName` to both the `payment_method` and `shipping_method` entities.
This `technicalName` property will serve as a unique identifier for payment and shipping methods, significantly simplifying the identification process.

While the `technicalName` field will be optional within the database and API to ensure backward compatibility, it will be made mandatory in the Administration.
This ensures that merchants will update their payment and shipping methods accordingly for the upcoming requirement.
An unique index will ensure uniqueness.
Starting from version 6.7.0.0, this `technicalName` field will also become required within the database and the API.

As part of the database migration process, the `technicalName` field will be automatically generated for the default payment and shipping methods provided by Shopware, as illustrated below:

| Type     | Name             | Technical Name          |
|----------|------------------|-------------------------|
| Payment  | Debit            | payment_debitpayment    |
| Payment  | Invoice          | payment_invoicepayment  |
| Payment  | Cash on Delivery | payment_cashpayment     |
| Payment  | Pre Payment      | payment_prepayment      |
| Shipping | Standard         | shipping_standard       |
| Shipping | Express          | shipping_express        |

Furthermore, all payment and shipping methods provided by apps will also benefit from the automatic generation of their `technicalName`.
This generation will be based on the app's name and the `identifier` defined for the payment method in the manifest:

| App Name | Identifier         | Technical Name                    |
|----------|--------------------|-----------------------------------|
| MyApp    | my_payment_method  | payment_MyApp_my_payment_method   |
| MyApp    | my_shipping_method | shipping_MyApp_my_shipping_method |

## Consequences
Plugin developers will be required to supply a `technicalName` for their payment and shipping methods, at least beginning with version 6.7.0.0.

Merchants must review their custom created payment and shipping methods for the new `technicalName` property and update their methods through the administration accordingly.

It is essential to exercise caution when modifying the `technicalName` through the administration, as such changes could potentially disrupt existing integrations.
