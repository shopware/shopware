---
title: Add conditional to toggleShippingSelector
issue: NEXT-23961
author: Stefan Regenauer
author_email: regenauer@mothership.de
author_github: @sr-mothership
---

# Storefront
* Added null check to `shippingToggleSelector` for offcanvas cart in `src/Storefront/Resources/app/storefront/src/plugin/offcanvas-cart/offcanvas-cart.plugin.js` to prevent console errors when the class is not found.
