---
title: Add the login required annotation
issue: NEXT-11963
---
# Core
*  Added `LoginRequired` class at `\Core\Framework\Routing\Annotation\LoginRequired`.
*  Added `\Core\Framework\RoutingSalesChannelRequestContextResolver::validateLogin()` private method, for validate a customer in the SaleChannelContext.
*  Changed all validate code Customers in `SalesChannelContext` by using LoginRequired annotation at store-api route.
___
# Storefront
*  Deprecated `\Storefront\Controller\StorefrontController::denyAccessUnlessLoggedIn()` use `@LoginRequired` instead
*  Changed all validate code customers in `SalesChannelContext` by using LoginRequired annotation at storefront controller.
