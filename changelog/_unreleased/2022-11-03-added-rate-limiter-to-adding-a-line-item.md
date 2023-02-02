---
title: Added rate limiter to adding a line item
issue: NEXT-23422
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Added `cart_add_line_item` to rate limiter configuration in `Shopwar\Core\Framework\Resources\config\packages\shopware` 
* Added constant `CART_ADD_LINE_ITEM` in `Shopware\Core\Framework\RateLimiter\RateLimiter`.
* Added rate limiter `Shopware\Core\Framework\RateLimiter\Policy\SystemConfigLimiter` and policy type `system_config` to allow limitation configuration with `SystemConfigService`
* Added policy type `system_config` to `Shopware\Core\Framework\RateLimiter`
___
# API
* Added rate limitation for api route `store-api.checkout.cart.add`
