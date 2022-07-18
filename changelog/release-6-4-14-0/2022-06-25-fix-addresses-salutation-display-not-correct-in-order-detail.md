---
title: Fix addresses salutation display is not correct in order detail
issue: NEXT-22127
---
# Administration
* Changed `orderCriteria` in `src/module/sw-order/view/sw-order-detail-base/index.js` to add association `addresses.salutation`.
