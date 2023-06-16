---
title: Add storefront controller endpoints to change or delete multiple line items at once
issue: NEXT-00000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added method `Core\Checkout\Cart\SalesChannel\CartService::update` to update multiple line items at once
* Added method `Core\Checkout\Cart\SalesChannel\CartService::removeItems` to remove multiple line items at once
___
# Storefront
* Added controller endpoints `/checkout/line-item/delete` (`frontend.checkout.line-items.delete`) and `/checkout/line-item/update` (`frontend.checkout.line-items.update`) to remove or update multiple line items at once
