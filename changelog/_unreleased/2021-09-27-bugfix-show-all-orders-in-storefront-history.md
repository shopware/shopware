---
title: Bugfix: show all orders in storefront history
issue: NEXT-17607
author: Marcel Tams
author_email: marcel.tams@networkteam.com 
author_github: amtee
---
# Core
* Changed method `Shopware\Core\Checkout\Order\SalesChannel\OrderRoute::load()` due to a misleading condition that resulted in the removal of orders updated or created more than 30 days ago from the latest order.
