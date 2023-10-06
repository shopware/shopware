---
title: Fix active ShippingMethod in Offcanvas-Cart
issue: NEXT-19950
author: David Fecke
author_github: @leptoquark1
---
# Storefront
* Added `activeShipping` variable in `Resources/views/storefront/component/checkout/offcanvas-cart-summary.html.twig` to restore its scope as needed in block `component_offcanvas_summary_content_shipping`.
