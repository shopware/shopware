---
title: Deprecate account order detail page
---
# Storefront
* Deprecated without replacement
    * `Page/Account/Order/AccountOrderDetailPage.php`
    * `Page/Account/Order/AccountOrderDetailPageLoadedEvent.php`
    * `Page/Account/Order/AccountOrderDetailPageLoadedHook.php`
    * `Page/Account/Order/AccountOrderDetailPageLoader.php`
* Deprecated route `widgets.account.order.detail`
___
# Next Major Version Changes
## Remove route `widgets.account.order.detail`:
* Remove all references to `widgets.account.order.detail` and ensure that affected components handle navigation and display correctly
