---
title: Fix Shopping Experience demo entity loading
issue: NEXT-000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Administration
* Deprecated `sw-cms-detail.addFirstSection` and `sw-cms-detail.addAdditionalSection` use `sw-cms-detail.onAddSection` instead
* Deprecated `sw-cms-detail.loadFirstDemoEntity` use `sw-cms-detail.onDemoEntityChange` instead
* Deprecated `sw-cms-detail.loadDemoCategoryMedia` as the category media will be loaded using an association and therefore also be shown in the administration
* Changed the product criteria of Shopping experience demo products, to only load main variant products, to show the correct names and descriptions
* Changed the behavior of the Shopping experience listing preview element, to show only a limited amount of products when the selected preview category has less then 8 products

