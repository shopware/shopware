---
title: Redirect direct guest login call
issue: NEXT-32170
---
# Storefront
* Changed `AuthController::guestLoginPage` to redirect to login page if the url is called directly without a redirect parameter.
