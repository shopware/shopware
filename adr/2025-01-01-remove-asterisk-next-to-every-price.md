---
title: Remove the asterisk (*) next to every price and replace it with actual text
date: 2025-01-01
area: framework
tags: [storefront, prices, accessibility]
---

## Context

Currently, all product prices that are displayed in the default Storefront have an asterisk (*) next to them, for example: `€ 50,00 *`
This asterisk refers to the tax and shipping costs information in the page footer `* All prices incl. VAT plus shipping costs and possible delivery charges, if not stated otherwise.`

Using the asterisk (*) next to every price has several downsides that we want to address:

### Footer text not always in viewport

When adding products to the shopping cart from within the listing the text might never be recognized.

### Redundant and confusing information

In some areas, the asterisk (*) referring to the footer text is more confusing than helpful. For example:
* On the product detail page the "tax and shipping information" link is displayed already right underneath the price.
* Inside the summary of the shopping cart the "tax and shipping information" is already part of the summary itself.

## Decision

## Consequences
