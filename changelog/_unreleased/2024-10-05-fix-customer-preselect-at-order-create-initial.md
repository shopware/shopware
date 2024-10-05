---
title: fix-customer-preselect-at-order-create-initial
issue: NEXT-36826
author: Robin Valley
author_email: r.valley@basecom.de
author_github: @rvalley98
---
# Administration
* change param `customer` to `customerId` from method `navigateToCreateOrder` in `Resources/app/administration/src/module/sw-customer/view/sw-customer-detail-order/index.js`
* add customer repository call with customerId from method `createdComponent` in `Resources/app/administration/src/module/sw-order/view/sw-order-create-initial/index.js`
* add optional param customerId for component `sw-order-create-initial` in `Resources/app/administration/src/module/sw-order/index.js`
