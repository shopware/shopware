---
title: Add missing star for gross price
issue: NEXT-12484
---
# Storefront
* Changed block `component_offcanvas_summary_total_value` in `storefront/component/checkout/offcanvas-cart-summary.html.twig` to add the star after the Subtotal price on off-canvas
* Changed block `component_offcanvas_summary_tax_info` in `storefront/component/checkout/offcanvas-cart-summary.html.twig` to add the star before the description text on on off-canvas
* Changed block `page_checkout_summary_total_value` in `storefront/page/checkout/summary.html.twig` to only show star after grand total if the current tax is "gross" on shopping cart page
