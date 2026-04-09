---
title: Add createdAt column to customer list in administration
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Administration
* Added `createdAt` column in `Resources/app/administration/src/module/sw-customer/page/sw-customer-list/index.js` to the `getCustomerColumns` array
* Changed `createdAt` column in `Resources/app/administration/src/module/sw-customer/page/sw-customer-list/index.js` to be the default sort key
* Added `createdAt` column in `Resources/app/administration/src/module/sw-customer/page/sw-customer-list/sw-customer-list.html.twig` to the data grid
