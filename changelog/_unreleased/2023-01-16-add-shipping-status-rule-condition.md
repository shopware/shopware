---
title: Add shipping status rule condition
issue: NEXT-19751
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @LarsKemper

---
# Core
* Added new rule condition `OrderDeliveryStatusRule`
___
# Administration
* Added new `orderDeliveryStatus` rule condition to the `condition-type-data-provider.decorator`
* Created new service file `criteria-helper.service.js` in `src/app/service`
* Added function `createCriteriaFromArray` to `src/app/service/criteria-helper.service.js`
* Added function `parseFilters` to `src/app/service/criteria-helper.service.js`
* Changed method `getBind` in `src/app/mixin/generic-condition.mixin.js`
