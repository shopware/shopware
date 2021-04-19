---
title: Add old Filter fields to customer and order module
issue: NEXT-14369
---
# Administration
*  Changed `changeValue` method in `src/app/component/filter/sw-existence-filter/index.js` to handle fixed type and options filter
*  Changed `changeValue` method in `src/app/component/filter/sw-multi-select-filter/index.js` to handle fixed type and options filter
*  Changed `listFilters` computed property in `src/module/sw-order/page/sw-order-list/index.js`, `src/module/sw-customer/page/sw-customer-list/index.js` to add old filters to filter panel
*  Deprecated `affiliateCodeFilter`, `campaignCodeFilter` data variable, `onChangeAffiliateCodeFilter`, `onChangeCampaignCodeFilter` in `src/module/sw-order/page/sw-order-list/index.js`
*  Deprecated `affiliateCodeFilter`, `campaignCodeFilter`, `showOnlyCustomerGroupRequests` data variable, `onChangeAffiliateCodeFilter`, `onChangeCampaignCodeFilter`, `onChangeRequestedGroupFilter` in `src/module/sw-order/page/sw-order-list/index.js`
