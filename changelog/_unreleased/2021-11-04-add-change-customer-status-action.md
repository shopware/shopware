---
title: Add change customer status action
issue: NEXT-18193
---
# Core
* Added `Shopware/Core/Content/Flow/Dispatching/Action/ChangeCustomerStatusAction` class to handle change customer status flow action.
___
# Administration
* Added component `sw-flow-change-customer-status-modal` to show a modal that allows changing customer group for customer.
* Added `getCustomerStatusDescription` function at `module/sw-flow/component/sw-flow-sequence-action/index.js` to get customer status description.
* Added `CHANGE_CUSTOMER_STATUS` into action list at `module/sw-flow/constant/flow.constant.js`.
* Added change customer status action icon and title at `module/sw-flow/service/flow-builder.service.js`.
