---
title: Fix timezone
issue: NEXT-31486
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Administration
* Changed `\Shopware\Administration\Controller\DashboardController::orderAmount` signature that timezone is no more part of the route path. This is now part of the request query string.    
