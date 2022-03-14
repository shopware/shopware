---
title: Add equalsAll filter to dynamic product groups
issue: NEXT-17094
author: Krispin Lütjann
author_email: k.luetjann@shopware.com
author_github: King-of-Babylon
---
# Core
* Added parsing type `equalsAll` to the `QueryStringParser` in `Framework/DataAbstractionLayer/Search/Parser`
___
# Administration
* Added `equalsAll` and `notEqualsAll` condition to the `productFilterTypes` and to the `uuid` `operatorSets` of `Resources/app/administration/src/app/service/product-stream-condition.service.js`
* Changed the visibility of the multi id select field for the `equalsAll` and `notEqualsAll` condition
