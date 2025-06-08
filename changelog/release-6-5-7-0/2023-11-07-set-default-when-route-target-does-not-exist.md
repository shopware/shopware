---
title: Set default target when route does not exist
issue: NEXT-31474
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Storefront
* Changed for route `/checkout/language`, we set a default target when target is not found via router (`RouteNotFoundException`). The default target is `frontend.home.page` also params are reset when target is invalid.
