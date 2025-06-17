---
title: Allow storefront routes to have different prefixes
author: Falko Hilbert
author_email: fhilbert@viosys.com
author_github: @FalkoHilbert
---

# Core

* Changed `\Shopware\Storefront\Framework\Routing\Router::isStorefrontRoute` check, so that the route name is no longer relevant.

___

# Upgrade Information

## Changed router check for storefront routes

The check of the `\Shopware\Storefront\Framework\Routing\Router::isStorefrontRoute` method has been modified to no longer rely on the route name.
This allows storefront routes to have different prefixes than the previously hardcoded `frontend.`, `widgets.` and `payment.` prefixes.
