---
title: Dynamic product group get error when using is equal along with product's association.
issue: NEXT-10277
---
# Administration
* Change property allowed list of product from `manufactureId` to `manufacture` at `/src/module/sw-product-stream/component/sw-product-stream-filter/index.js`
* Added `sw-product-stream.filter.values.manufacturer` as `Manufacture` at `/src/module/sw-product-stream/snippet/en-GB.json`
* Added `sw-product-stream.filter.values.manufacturer` as `Hersteller` at `/src/module/sw-product-stream/snippet/de-DE.json`
* Changed the second `sw-entity-multi-id-select` at block `sw_product_stream_value_entity_multi_value` to display for entity is product only
* Added the new `sw-entity-multi-id-select` at block `sw_product_stream_value_entity_multi_value` to display for product's association
