---
title: Get latest order status after saving changes
issue: NEXT-21670
---
# Administration
* Changed `onSave` method in `sw-bulk-edit-order` component to call getting latest order status.
* Added `getLatestOrderStatus` method in `sw-bulk-edit-order` component to get latest order status.
* Changed `:maximum-select-items` prop of `sw-data-grid` in `sw-order-list` from `1000` to `100`
