---
title: Silence and log exceptions of In-App purchases
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `InAppPurchaseProvider` to log `JWTException`
* Changed `JWTDecoder` to correctly wrap contraint violations into `JWTException`
