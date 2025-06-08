---
title: Fix payment changeable in account order history with cancelled order
issue: NEXT-17382
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Storefront
* Changed `page/account/order-history/order-item.html.twig` so the change payment button is not available if an order is cancelled.
