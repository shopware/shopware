---
title: Extend property select to show property group
issue: NEXT-12264
author_github: @Dominik28111
---
# Administration
* Added event `select-collapsed` and `search-term-change` to `sw-entity-multi-id-select`
* Changed method `getKey()` in `sw-entity-multi-select/index.js` to return associated property group name
* Changed method `onInputSearchTerm()` in `sw-entity-multi-select/index.js` to update data prop `searchTerm` and emit event `search-term-change`
* Changed method `getKey()` in `sw-entity-single-select/index.js` to return associated property group name
* Added data prop `searchTerm` in `sw-condition-line-item-property/index.js`
* Added computed prop `optionCriteria` in `sw-condition-line-item-property/index.js` to add property group association to criteria
* Changed method `createdComponent` in `sw-condition-line-item-property/index.js` to use `optionCriteria` for the entity collection and repository search
* Added method `setSearchTerm()` in `sw-condition-line-item-property/index.js`
* Added method `onSelectCollapsed()` in `sw-condition-line-item-property/index.js`
* Changed block `sw-condition-line-item-property.html.twig` in `sw_condition_line_item_properties_field_identifiers` it now uses `sw-entity-multi-select` to remove add option field for properties
* Removed `properties`from product blacklist in `product-stream-condition.service.js` to give the possibility to filter for properties in dynamic product groups
* Added data prop `searchTerm` in `sw-product-stream-value/index.js`
* Changed computed prop `productCriteria` to add property group association to criteria
* Added method `setSearchTerm()` in `sw-product-stream-value/index.js`
* Added method `onSelectCollapsed()` in `sw-product-stream-value/index.js`
* Added event listener `select-collapsed` and `search-term-change` to `sw-entity-single-select` in block `sw_product_stream_value_entity_single_value` in `sw-product-stream-value.html.twig`
* Added prop `criteria` to `sw-entity-single-select` in block `sw_product_stream_value_entity_single_value` in `sw-product-stream-value.html.twig` to use `productCriteria`
* Changed block `sw_product_stream_value_entity_multi_value` in `sw-product-stream-value.html.twig` to use new `sw-entity-multi-id-select` to display property groups
