---
title: Fix browser back behavior on Storefront logged in routes
issue: NEXT-17938
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com 
---
# Storefront
* Added new annotation `@Shopware\Storefront\Framework\Routing\Annotation\MustRevalidate` to set cache behavior to always reload on browser back
* Changed the following routes to used the new annotation `@MustRevalidate`:
  * `frontend.account.order.page`
  * `frontend.account.order.single.page`
  * `frontend.account.edit-order.page`
  * `frontend.account.payment.page`
  * `frontend.account.home.page`
  * `frontend.account.profile.page`
  * `frontend.account.address.page`
  * `frontend.account.address.create.page`
  * `frontend.account.address.edit.page`
  * `frontend.account.login.page`
  * `frontend.account.guest.login.page`
  * `frontend.checkout.cart.page`
  * `frontend.checkout.confirm.page`
  * `frontend.checkout.finish.page`
  * `frontend.account.register.page`
  * `frontend.checkout.register.page`
  * `frontend.account.customer-group-registration.page`
