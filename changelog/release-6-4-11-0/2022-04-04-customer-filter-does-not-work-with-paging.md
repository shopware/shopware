---
title: Fix Customer filter does not work with paging
issue: NEXT-20627
---
# Administration
* Changed method `getList` in `src/module/sw-customer/page/sw-customer-list/index.js` to update `newCriteria` with new `criteria`
