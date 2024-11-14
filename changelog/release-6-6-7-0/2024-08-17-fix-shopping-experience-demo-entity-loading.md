---
title: Fix Shopping Experience demo entity loading
issue: NEXT-38243
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Administration
* Removed `sw-cms-detail.addFirstSection` and `sw-cms-detail.addAdditionalSection`, use `sw-cms-detail.onAddSection` instead
* Removed `sw-cms-detail.loadFirstDemoEntity`, use `sw-cms-detail.onDemoEntityChange` instead
* Removed `sw-cms-detail.loadDemoCategoryMedia` as the category media will be loaded using an association and therefore also be shown in the administration
* Changed the product criteria of Shopping experience demo products to only load main variant products, to show the correct names and descriptions
* Changed the behaviour of the Shopping Experience listing preview element to show only a limited amount of products, when the selected preview category has less than 8 products
