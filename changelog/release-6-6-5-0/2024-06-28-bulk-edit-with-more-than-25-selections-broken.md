---
title: Bulk edit with more than 25 selections broken
issue: NEXT-36534
---
# Administration
* Changed `onSave` method from `sw-bulk-edit-customer` module; `bulkEdit` method of the `bulkEditApiFactory`'s customer handler is now called for each payload chunk when `syncData` has length.
