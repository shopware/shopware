---
title: Added pagination for account orders overview
issue: NEXT-15212
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed allowed methods of `frontend.account.order.page` to include `POST` and allow `XmlHttpRequest` for ajax pagination
* Changed getting page and limit parameters from either query or post for ajax pagination in `AccountOrderPageLoader`
* Changed total calculation to `Criteria::TOTAL_COUNT_MODE_EXACT` in `AccountOrderPageLoader` for ajax pagination
___
# Storefront
* Added ajax pagination to `page/account/order-history/index.html.twig`
