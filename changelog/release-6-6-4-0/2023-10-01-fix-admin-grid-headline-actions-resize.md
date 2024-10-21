---
title: Fix admin grid headline actions resizing on using context menus
issue: NEXT-30845
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Administration
* Changed CSS-selectors like `:not(.sw-data-grid__cell--actions)` for icon coloring and sizing on to also contain `:not(.sw-data-grid__cell--header)` so context buttons in `sw-customer-list`, `sw-order-list`, `sw-product-list`, `sw-promotion-v2-list`, `sw-property-list`, `sw-settings-country-list`, `sw-settings-shipping-list` and `sw-settings-tax-list` do not resize in their active state
