---
title: Getting errors when removing tags from order
issue: NEXT-24871
---
# Administration
* Changed method `createdComponent` from `/administration/src/module/sw-order/component/sw-order-general-info/index.js` to clone `order.tags` and assigned it to data `tagCollection`.
