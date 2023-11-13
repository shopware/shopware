---
title: Handle exception when register without error route
issue: NEXT-27079
---
# Storefront
* Changed method `register` of class `Shopware\Storefront\Controller\RegisterController` to fallback to `frontend.account.register.page` if getting `errorRoute` parameter is empty.

