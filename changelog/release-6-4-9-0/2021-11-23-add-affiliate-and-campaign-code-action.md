---
title: Add an affiliate and campaign code action
issue: NEXT-18082
---
# Core
* Added `Shopware/Core/Content/Flow/Dispatching/Action/AddCustomerAffiliateAndCampaignCodeAction` class to handle add affiliate and campaign code to Customer action.
* Added `Shopware/Core/Content/Flow/Dispatching/Action/AddOrderAffiliateAndCampaignCodeAction` class to handle add affiliate and campaign code to Order action.
___
# Administration
* Added component `sw-flow-affiliate-and-campaign-code-modal` to show a modal that allows adding affiliate and campaign code for customer/order.
* Added `getAffiliateAndCampaignCodeDescription` function at `src/module/sw-flow/component/sw-flow-sequence-action/index.js` to get affiliate and campaign code description.
* Added `ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE` and `ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE` into action list at `src/module/sw-flow/constant/flow.constant.js`.
* Added adding affiliate and campaign code action icon and title at `src/module/sw-flow/service/flow-builder.service.js`.
* Added `convertEntityName` function at `src/module/sw-flow/service/flow-builder.service.js`.
* Deprecated `convertEntityName` function at `src/module/sw-flow/component/modals/sw-flow-tag-modal/index.js` and `src/module/sw-flow/component/modals/sw-flow-set-entity-custom-field-modal/index.js`.
* Added watcher `entity` at `src/module/sw-flow/component/modals/sw-flow-set-entity-custom-field-modal/index.js`.
