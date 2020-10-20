---
title:              Added individual sorting for cms elements
issue:              NEXT-10365
author:             Lennart Tinkloh
author_email:       l.tinkloh@shopware.com
author_github:      lernhart
---
# Administration
* Added support to define custom sortings for a specific cms element in shopping experience
* Added `sw-cms-el-config-product-listing` component
* Added `sw-cms-el-config-product-listing-config-delete-modal` component
* Added `sw-cms-el-config-product-listing-config-sorting-grid` component
* Added `hideLabels` boolean prop to `sw-entity-multi-select` to hide labels of the selected entities inside the select field. This will be handed over to the `sw-select-selction-list` component
* Added `hideLabels` prop inside the `sw-select-selection-list` component
___
# Storefront
* Added `showSorting` to `src/Storefront/Resources/views/storefront/component/sorting.html.twig` to hide / show sortings dropdown
