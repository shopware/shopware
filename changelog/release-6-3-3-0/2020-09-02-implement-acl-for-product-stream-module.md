---
title: Add ACL for product stream module
issue: NEXT-8927
---
# Administration
* Added ACL privileges to `sw-product-stream` module
* Added `allowDelete` as a slot prop to `delete-action` slot in `sw-entity-listing` component
* Added `disabled` prop to `sw-single-select` component
* Added `disabled` prop to `sw-entity-multi-id-select` component
* Added `disabled` prop to `sw-entity-multi-select` component
* Added `arrowFill` & `arrowBorder` computed property to `sw-arrow-field` component
* Added `getNoPermissionsTooltip` computed property to `sw-condition-and-container`, `sw-product-stream-detail` and `sw-condition-or-container` component
* Added `product_stream.viewer|editor|creator|deleter` as acl roles
* Added `disabled` prop to `sw-product-stream-field-select` & `sw-product-stream-value` component
