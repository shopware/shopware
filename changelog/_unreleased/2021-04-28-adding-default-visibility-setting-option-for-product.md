---
title: Adding default visibility setting option for product
issue: NEXT-14824
flag: FEATURE_NEXT_12437
---
# Administration
*  Added `salesChannel` computed property in `src/module/sw-settings-listing/page/sw-settings-listing/index.js` to get and set default selected sales channel
*  Added `salesChannel` watch property in `src/module/sw-settings-listing/page/sw-settings-listing/index.js` to handle config data when selecting sales channel
*  Added `fetchSalesChannelsSystemConfig` method in `src/module/sw-settings-listing/page/sw-settings-listing/index.js` to fetch sales channel config data from database
*  Added `saveSalesChannelVisibilityConfig` method in `src/module/sw-settings-listing/page/sw-settings-listing/index.js` to handle saving default sales channel data
*  Added `src/module/sw-settings-listing/component/sw-settings-listing-visibility-detail` to display visibility setting modal content
