---
title: Add change customer group action
issue: NEXT-17975
flag: FEATURE_NEXT_17973
---
# Core
* Added `Shopware/Core/Content/Flow/Dispatching/Action/ChangeCustomerGroupAction` class to handle change customer group flow action.
___
# Administration
* Added component `sw-flow-change-customer-group-modal` to show a modal that allows changing customer group for customer.
* Added `getCustomerGroupDescription` function at `module/sw-flow/component/sw-flow-sequence-action/index.js` to get customer group description.
* Added `CHANGE_CUSTOMER_GROUP` into action list at `module/sw-flow/constant/flow.constant.js`.
* Added `customerGroupRepository` and `customerGroupCriteria` functions at `module/sw-flow/page/sw-flow-detail/index.js` to get customer group data if customer group action is exist.
* Added change customer action icon and title at `module/sw-flow/service/flow-builder.service.js`.
