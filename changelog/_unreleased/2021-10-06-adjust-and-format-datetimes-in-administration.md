---
title: Adjust and format dateTimes in administration
issue: NEXT-17979
author: Ioannis Pourliotis
author_email: dev@pourliotis.de
author_github: @PheysX
---
# Administration
* Changed the `createdAt` column to `orderDateTime` in `src/Administration/Resources/app/administration/src/module/sw-customer/view/sw-customer-detail-order/sw-customer-detail-order.html.twig`.
* Added the `sw-time-ago` component to `orderDateTime` column in `src/Administration/Resources/app/administration/src/module/sw-customer/view/sw-customer-detail-order/sw-customer-detail-order.html.twig`.
* Deprecated block `sw_customer_detail_order_card_grid_columns_date` in `src/Administration/Resources/app/administration/src/module/sw-customer/view/sw-customer-detail-order/sw-customer-detail-order.html.twig`, will be removed in v6.5.0. Use `sw_customer_detail_order_card_grid_columns_order_date_time` instead.
* Added dateTime format to `lastLogin` column in `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-base-info/sw-customer-base-info.html.twig`.
* Removed hours and minutes from birthday in `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-base-info/sw-customer-base-info.html.twig`.
* Added jest test `src/Administration/Resources/app/administration/test/module/sw-customer/component/sw-customer-base-info.spec.js`.
