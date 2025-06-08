---
title: Change blacklist to allow list of properties in custom product groups
issue: NEXT-12158
 
---
# Administration
* Added following new methods to `Resources/app/administration/src/app/service/product-stream-condition.service.js`
    * Added new method `isPropertyInAllowList`
    * Added new method `addToGeneralBlacklist`
    * Added new method `addToEntityBlacklist`
    * Added new method `removeFromGeneralBlacklist`
    * Added new method `removeFromEntityBlacklist`
    * Deprecated method `isPropertyInBlacklist`
    * Deprecated method `addToGeneralAllowList`
    * Deprecated method `removeFromGeneralAllowList`
    * Deprecated method `removeFromEntityAllowList`
