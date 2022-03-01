---
title: Entity select advanced selection implemented
issue: NEXT-12411
author: Malte Janz
author_email: m.janz@shopware.com
author_github: MalteJanz
---
# Administration
* Added `sw-entity-advanced-selection-modal` component
* Added `sw-advanced-selection-product` component
* Changed `sw-card-filter` to accept a new optional property `initialSearchTerm` which sets the search term on component creation
* Changed `sw-entity-multi-select` component:
  * to accept an optional `advancedSelectionComponent` string as a property.
  * added method `onAdvancedSelectionSelectionSubmit(itemArray)` to apply the provided selection
  * adjusted template to render the `advancedSelectionComponent` and another item in the search result list if necessary
* Changed `sw-entity-single-select` component:
    * to accept an optional `advancedSelectionComponent` string as a property.
    * adjusted template to render the `advancedSelectionComponent` and another item in the search result list if necessary
* Changed `sw-condition-line-item` component to make use of the newly added advanced selection functionality for products
* Changed `sw-condition-line-items-in-cart` component to make use of the newly added advanced selection functionality for products
* Changed `sw-condition-line-item-with-quantity` component to make use of the newly added advanced selection functionality for products
* Changed `sw-product-stream-value` component to make use of the newly added advanced selection functionality for product
