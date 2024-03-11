---
title: Merchant registration
date: 2020-08-14
area: customer-order
tags: [merchant, registration, customer-group]
---

## Context

We have to provide a registration for merchant.
The definition of a customer, which is defined as a merchant, we want to realize via customer groups.
However, this is not "merchant" specific, because we do not react to "merchant customer groups" in any way in the core. In other words, we implement a customer group registration system.

The process should work as follows:
* The shop owner enables customer group registration for a customer group and generates an url
    * This url must be shared by the shop owner to customers (footer, social media, mails, etc.)
* Customer registers on an individual registration page on an individual url
* The customer will be created with the default customer group
* The shop operator can accept / decline the "merchant" registration in the admin

For this I would suggest the following: 
* At the customer we store another Foreign Key (desired customer group)
	* This is then considered in the StoreApiRoute and stored at the customer
* In Administration we extend the current customer module with an accept / decline button
* Upon activation, we switch the customer group of the customer and set "desired customer group" back to zero.

## Decision

### Headless Frontend

* Headless sales channel can resolve the url to get the foreign key using the seo-url store api route
* Call the customer-group-registration config endpoint with the foreign key to get the form configuration
* Sends a registration to customer registration endpoint with the `requestedGroupId`

## Consequences

* Registration always creates customer accounts even when the request will be declined.
