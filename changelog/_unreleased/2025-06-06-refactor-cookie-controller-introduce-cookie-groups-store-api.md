---
title: Refactor cookie controller, introduce cookie groups store-api
issue: 9451
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Core
* Changed `CookieController` to use the new `CookieRoute`.
___
# API
* Added `CookieRoute` to provide a new store-api endpoint `/store-api/cookie-groups`.
