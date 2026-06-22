---
title: Fix storefront method switchers with checkout gateway
issue: #16780
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Storefront
* Changed checkout cart and confirm page loading to resolve payment and shipping methods blocked by the checkout gateway before rendering the page.
* Changed fallback selection to use checkout gateway available methods, preferring the sales-channel default method when available.
