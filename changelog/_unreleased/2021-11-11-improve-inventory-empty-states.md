---
title: Improve inventory-related empty states
issue: NEXT-18464
author: Ramona Schwering
flag: FEATURE_NEXT_17546
author_github: @leichteckig
---
# Administration
* Changed save button in `sw-product-add-properties-modal` to be hidden if properties don't exist yet
* Changed empty state in `sw-product-add-properties-modal` to display its link as button
* Changed empty state in `sw-product-detail-reviews` to display its link as button
* Added a customised empty state to `sw-product-detail-variant` if no property are available yet
* Change the default empty in `sw-product-detail-variant` to use `sw-empty-state` component
* Added `sw-product-add-properties-modal` to `sw-product-detail-variant` to enable direct property assignment
