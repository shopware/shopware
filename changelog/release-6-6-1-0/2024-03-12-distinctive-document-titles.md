---
title: Provide distinctive document titles for each page
issue: NEXT-33682
---
# Storefront
* Added `translator` as constructor parameter to each of the following Pageloaders:
* Added a distinctive title to `metadata` of the pagees to the following Pageloaders:
  * `AccountLoginPageLoader`
  * `AccountEditOrderPageLoader`
  * `AccountOrderPageLoader`
  * `AccountOverviewPageLoader`
  * `AccountPaymentMethodPageLoader`
  * `AccountProfilePageLoader`
  * `AddressDetailPageLoader`
  * `AddressListingPageLoader`
  * `CheckoutCartPageLoader`
  * `CheckoutConfirmPageLoader`
  * `CheckoutFinishPageLoader`
  * `CheckoutRegisterPageLoader`
  * `SearchPageLoader`
* Deprecated `Storefront/Resources/views/storefront/page/account/register/meta.html.twig` for v6.7.0
* Deprecated `Storefront/Resources/views/storefront/page/checkout/cart/meta.html.twig` for v6.7.0
* Added the following snippets:
  * `account.registerMetaTitle`
  * `account.paymentMetaTitle`
  * `account.profileMetaTitle`
  * `account.ordersMetaTitle`
  * `account.overviewMetaTitle`
  * `account.addressCreateMetaTitle`
  * `account.addressEditMetaTitle`
  * `account.addressMetaTitle`
  * `account.completePaymentMetaTitle`
  * `account.cartMetaTitle`
  * `checkout.registerMetaTitle`
  * `checkout.confirmMetaTitle`
  * `checkout.finishMetaTitle`
  * `search.metaTitle`
