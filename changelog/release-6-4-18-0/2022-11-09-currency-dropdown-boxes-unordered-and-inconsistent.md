---
title: Currency dropdown boxes unordered and inconsistent
issue: NEXT-18924
---
# Administration
* Changed method `getLoadSalesChannelCriteria` in `src/module/sw-sales-channel/page/sw-sales-channel-detail/index.js` to apply alphabetical sorting for currency selection.
* Added computed `currencyCriteria` in `src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js` to apply alphabetical sorting for currency selection.
