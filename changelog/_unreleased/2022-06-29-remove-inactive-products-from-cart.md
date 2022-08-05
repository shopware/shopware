---
title: Remove inactive and deleted products from cart
issue: NEXT-22182
author: Micha Hobert
author_email: info@the-cake-shop.de
author_github: Isengo1989
---
# Core
* Changed `ProductCartProcessor::collect` to set dataTimestamp to `null` if lineItem id is not inside of the productIds from the productGateway
