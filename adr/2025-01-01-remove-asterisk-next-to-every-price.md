---
title: Remove the asterisk next to every price and replace it with actual text
date: 2025-01-01
area: framework
tags: [storefront, prices, accessibility]
---

## Context

Currently, all product prices that are displayed in the default Storefront have an asterisk `*` next to them, for example: `€ 50,00 *`
This asterisk refers to the tax and shipping costs information in the page footer `* All prices incl. VAT plus shipping costs and possible delivery charges, if not stated otherwise.`

Using the asterisk `*` next to every price has several downsides that we want to address:

### Footer text not always in viewport

When adding products to the shopping cart from within the listing the text might never be recognized.

### Redundant and confusing information

In some areas, the asterisk `*` referring to the footer text is more confusing than helpful. For example:
* On the product detail page the "tax and shipping information" link is already displayed right underneath the price.
* Inside the summary of the shopping cart the "tax and shipping information" is already part of the summary itself.
* When a form is shown on the same page, the asterisks `*` of the required fields are conflicting with the asterisks `*` of the prices.
* In general the asterisk `*` might give the impression that the shown price is not the actual price and might change later.

### Accessibility issues

The asterisk `*` is only plain text at the moment and has no actual relationship to its corresponding footer info text.
A screen reader will always read "50 euros star" without further context. For a screen reader user, the asterisk is not accessible.

## Decision

The asterisk `*` next to every price will be removed because of the reasons mentioned above.
In most areas of the Storefront, the information that the asterisk `*` refers to is already given, and it is therefore redundant.
In areas where the asterisk `*` was actually needed, it will be replaced by the actual text "Prices incl. VAT plus shipping costs" instead to resolve the accessibility issues.

### Affected areas

| Area                                       | Explanation                                                                                 |
|--------------------------------------------|---------------------------------------------------------------------------------------------|
| Shopping cart and order line items         | Asterisk removed. Info is already shown in the cart summary.                                |
| Shopping cart summary                      | Asterisk removed. Info is already part of the cart summary itself. (shipping, taxes)        |
| Header cart widget                         | Asterisk removed. Info is not needed because no product can be added to the cart here.      |
| Header search suggest box                  | Info is not needed because no product can be added to the cart here.                        |
| Product-box (listing, product slider etc.) | Info is displayed as text instead when setting `core.listing.allowBuyInListing` is enabled. |
| Buy-widget on product detail page          | Info is already shown on the product detail page underneath the price.                      |

The changes can be activated by using the `ACCESSIBILITY_TWEAKS` feature flag and will be the default in the upcoming major version `v6.7.0`.

See `2025-01-16-remove-the-asterisk-next-to-every-price.md` for the technical changelog.

## Consequences

* With the next major `v6.7.0` or active `ACCESSIBILITY_TWEAKS` the asterisk `*` next to every price will be removed.
* Product boxes that allow an "Add to cart" action from within the product listing will display the "Prices incl. VAT plus shipping costs" information as text instead. Only displayed when setting `core.listing.allowBuyInListing` is enabled.
