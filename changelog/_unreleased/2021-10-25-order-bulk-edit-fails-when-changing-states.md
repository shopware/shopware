---
title: Order bulk edit fails when changing states
issue: NEXT-18077
---
# Administration
*  Changed `onProcessData` in `administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order/index.js` to add selected status to the request body.
