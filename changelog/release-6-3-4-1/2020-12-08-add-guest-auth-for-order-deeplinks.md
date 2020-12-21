---
title: Add guest auth for order deeplinks
issue: NEXT-12359
author: Timo Altholtmann 
---
# Core
* Added `GuestNotAuthenticatedException`
* Added `WrongGuestCredentialsException`
___
# Storefront
* Added route `frontend.account.guest.login.page` for guest authentication
* Added `LoginRequired` annotation for route `frontend.account.order.single.document`
* Added template `src/Storefront/Resources/views/storefront/page/account/guest-auth.html.twig`
