---
title: add custom fields to condition in dynamic product group
issue: NEXT-9148
---
# Administration
* Changed computed property `fields` in `src/module/sw-product-stream/component/sw-product-stream-filter/index.js` to have correct getter.
* Added method `isItemACustomField` in `src/module/sw-product-stream/component/sw-product-stream-filter/index.js` to check if field is a custom field.
* Changed computed `fieldDefinition` in `src/module/sw-product-stream/component/sw-product-stream-value/index.js` to get correct field definition.
* Changed method `createProductStream` in `src/module/sw-product-stream/page/sw-product-stream-detail/index.js` to get custom fields when creating product stream filters.
* Changed method `getProductCustomFields` in `src/module/sw-product-stream/page/sw-product-stream-detail/index.js` to add a prefix `customFields.` to value of custom field.
* Added method `getCustomFieldLabel` in `src/module/sw-product-stream/page/sw-product-stream-detail/index.js` to get custom field label.
