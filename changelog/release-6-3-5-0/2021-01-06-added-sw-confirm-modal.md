---
title: Added sw-confirm-modal and added sw-one-to-many-grid functionality
issue: NEXT-12514
---
# Administration
*  Added new default component `sw-confirm-modal` as new solution for very common confirm modal situations
*  Added `deleteItems` and `deleteItemsFinish` methods to `sw-one-to-many-grid` like it is already implemented in `sw-entity-listing` to easily delete the selected grid elements
*  Changed `deleteItems` method of `sw-entity-listing` to delete every entry in one request instead of one for each