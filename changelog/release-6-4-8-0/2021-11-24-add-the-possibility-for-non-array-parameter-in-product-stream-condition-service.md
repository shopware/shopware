---
title: Add the possibility for non-array parameter in product-stream-condition-service
issue: NEXT-18959
---
# Administration
* Changed behaviour of the following methods in `product-stream-condition.service.js` to allow non-array properties as well:
  * `addToEntityAllowList`
  * `addToGeneralAllowList`
  * `removeFromGeneralAllowList`
  * `removeFromEntityAllowList`