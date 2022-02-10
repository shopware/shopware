---
title: Fix paginating for sw-single-select in zoomed browsers
issue: NEXT-18354
author: Simon Vorgers
author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# Administration
* Changed method `onScroll` in `sw-select-result-list` to emit `paginate` a little less accurate
* Changed method `variantCriteria` in `sw-product-variants-delivery-listing` to filter for options names
* Added method `onSelectCollapsed`, `setSearchTerm` in `sw-product-variants-delivery-listing`