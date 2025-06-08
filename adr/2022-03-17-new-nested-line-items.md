---
title: New templates for line items and nested line items
date: 2022-03-17
area: storefront
tags: [storefront, line-items, checkout]
---

## Context

In the storefront there are multiple templates, which display line items in different areas of the shop.
Line items can be products, discounts, container items or custom line item types added by apps/extensions.

Currently, there are over ten different templates which are used to render line items in different situations.
This current implementation has a few downsides we want to address with the next major version.
* Templates are sometimes written as independent templates, while copying over chunks of code from other line item templates. 
* Sometimes the templates extend other line item templates instead.
* Inconsistent naming, templates are named `checkout-item` while the CSS-classes/markup inside the template itself is named `cart-item`
* Additional templates are needed to display nested line items.
* Nested line items only displayed as bullet points with text, not as "real" line items.
* Large templates files with many if-else conditions to distinguish between product, discount etc.

## Decision

To address the issues mentioned above, we decided to refactor the different line item templates into a single line item base template: `Resources/views/storefront/component/line-item/line-item.html.twig`.

* All shop areas will be able to use the new template. The appearance (e.g. offcanvas) can be toggled via configuration variables.
* The naming will be changed to `line-item` for more consistency. A line item has not always to be inside a shopping cart.
* No more additional templates needed for children (nested line items), the base template includes itself recursively now.
* All known line item types (product, container, discount) get their own template to future-proof for more line item types and better readability.
* Less maintenance effort for extensions which may want to include custom line item types.

## Consequences

* All storefront line items in platform will use the new base template `Resources/views/storefront/component/line-item/line-item.html.twig`.
* The appearance of line items displayed inside the offcanvas will be unified with the mobile appearance of line items in the regular cart.
* If you are extending one if the line item templates listed below, you will need to use the line item base template `Resources/views/storefront/component/line-item/line-item.html.twig` instead.
    * `Resources/views/storefront/page/checkout/checkout-item.html.twig`
    * `Resources/views/storefront/page/checkout/checkout-item-children.html.twig`
    * `Resources/views/storefront/page/checkout/confirm/confirm-item.html.twig`
    * `Resources/views/storefront/page/checkout/finish/finish-item.html.twig`
    * `Resources/views/storefront/component/checkout/offcanvas-item.html.twig`
    * `Resources/views/storefront/component/checkout/offcanvas-item-children.html.twig`
    * `Resources/views/storefront/page/account/order/line-item.html.twig`
    * `Resources/views/storefront/page/account/order-history/order-detail-list-item.html.twig`
    * `Resources/views/storefront/page/account/order-history/order-detail-list-item-children.html.twig`
    * `Resources/views/storefront/page/checkout/checkout-aside-item.html.twig`
    * `Resources/views/storefront/page/checkout/checkout-aside-item-children.html.twig`
