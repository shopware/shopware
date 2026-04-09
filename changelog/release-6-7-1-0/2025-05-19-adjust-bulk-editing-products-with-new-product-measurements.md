---
title: adjust bulk editing products with new product measurements
issue: 8545
---
# Administration
* Changed the following components to add the new emit event `update:default-unit`:
    * `sw-bulk-edit-change-type-field-renderer`
    * `sw-bulk-edit-form-field-renderer`
* Changed `sw-bulk-edit-product` component to allow bulk editing of products with the new measurement system
* Changed `bulk-edit-product.handler` module to pass `context` into `bulkEdit` function
