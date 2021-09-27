---
title: Bugfix: show all orders in storefront history
author: Marcel Tams
author_email: marcel.tams@networkteam.com 
author_github: amtee
---
# Core
* In method `load` in file `src/Core/Checkout/Order/SalesChannel/OrderRoute.php`  there
was a misleading condition. Any `$deepLinkFilter` but false led to removing `orders` with last updated 
  or created older than 30 days the latest order.
  
  

