---
title: Fix small thumbnails in checkout
issue: NEXT-8609
author: Jakob Kruse
author_email: j.kruse@shopware.com
author_github: jakob-kruse
---
# Storefront
* Changed cart item thumbnails in `Resources/views/storefront/page/checkout/checkout-item.html.twig` by applying additional styles for class `.cart-item-img` to always be a square containing the cover image instead of varying sizes.
